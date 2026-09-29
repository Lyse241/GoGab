<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\OrderTransitionException;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private OrderWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
        $this->workflow = app(OrderWorkflow::class);
    }

    /**
     * Vérifie qu'une transition interdite lève l'exception et ne modifie rien.
     */
    private function assertForbidden(Order $order, OrderStatus $to, User $actor, string $messagePart, ?string $note = null): void
    {
        $before = $order->fresh();
        $histories = $order->statusHistories()->count();
        $notifications = \Illuminate\Notifications\DatabaseNotification::count();

        try {
            $this->workflow->transition($order, $to, $actor, $note);
            $this->fail("La transition vers {$to->value} aurait dû être refusée.");
        } catch (OrderTransitionException $e) {
            $this->assertStringContainsString($messagePart, $e->getMessage());
        }

        $after = $order->fresh();
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->delivery_id, $after->delivery_id);
        $this->assertSame($histories, $order->statusHistories()->count());
        $this->assertSame($notifications, \Illuminate\Notifications\DatabaseNotification::count());
    }

    public function test_placing_an_order_starts_pending_with_history_and_notifies_the_business(): void
    {
        $product = $this->store->products()->create(['name' => 'Poulet DG', 'price' => 6500]);

        $order = $this->workflow->place($this->client, [
            'store_id' => $this->store->id,
            'neighborhood_id' => Neighborhood::firstWhere('name', 'Glass')->id,
            'address_landmarks' => 'Près du marché',
            'subtotal' => 6500,
            'total_price' => 6500,
            'payment_method' => 'airtel_money',
        ], [['product_id' => $product->id, 'quantity' => 1, 'price' => 6500]]);

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertNull($order->delivery_id);
        $this->assertSame([OrderStatus::Pending], $order->statusHistories()->get()->pluck('status')->all());
        $this->assertSame(["Nouvelle commande {$order->reference}"], $this->notificationTitles($this->owner));
        $this->assertStringContainsString('6 500 FCFA', $this->owner->notifications()->first()->data['message']);
    }

    public function test_only_an_approved_client_can_place_an_order(): void
    {
        $this->expectException(OrderTransitionException::class);

        $this->workflow->place(User::factory()->pending()->create(['role' => 'client']), [], []);
    }

    public function test_full_happy_path_with_history_and_notifications(): void
    {
        $order = $this->makeOrder();
        $unavailable = $this->makeCourier('Indispo', Neighborhood::firstWhere('name', 'Louis'), available: false);

        $steps = [
            [OrderStatus::Accepted, $this->owner],
            [OrderStatus::Preparing, $this->owner],
            [OrderStatus::SearchingCourier, $this->owner],
            [OrderStatus::CourierAssigned, $this->courier],
            [OrderStatus::Delivering, $this->courier],
            [OrderStatus::Arrived, $this->courier],
            [OrderStatus::Delivered, $this->courier],
        ];

        foreach ($steps as [$to, $actor]) {
            $order = $this->workflow->transition($order, $to, $actor);
            $this->assertSame($to, $order->status);
        }

        $this->assertSame($this->courier->id, $order->delivery_id);

        // Une ligne d'historique par transition, avec son auteur.
        $history = $order->statusHistories()->get();
        $this->assertSame(array_column($steps, 0), $history->pluck('status')->all());
        $this->assertSame(array_map(fn (array $step) => $step[1]->id, $steps), $history->pluck('changed_by')->all());

        // Notifications de chaque étape aux bonnes personnes.
        $this->assertSame([
            'Commande acceptée',
            'Commande en préparation',
            'Commande livrée',
            'Commande récupérée',
            'Livreur trouvé',
            'Recherche d’un livreur',
            'Votre livreur est arrivé',
        ], $this->notificationTitles($this->client));
        $this->assertSame(['Commande livrée', 'Commande récupérée', 'Livreur assigné'], $this->notificationTitles($this->owner));
        // Annonce : livreurs validés et disponibles de la zone du commerce uniquement.
        $this->assertSame(['Nouvelle course disponible'], $this->notificationTitles($this->courier));
        $this->assertSame([], $this->notificationTitles($this->farCourier));
        $this->assertSame([], $this->notificationTitles($unavailable));
    }

    public function test_business_refuses_with_a_reason(): void
    {
        $order = $this->makeOrder();

        $this->assertForbidden($order, OrderStatus::Refused, $this->owner, 'Indiquez le motif');

        $order = $this->workflow->transition($order, OrderStatus::Refused, $this->owner, 'Rupture de poulet ce soir.');

        $this->assertSame(OrderStatus::Refused, $order->status);
        $this->assertSame('Rupture de poulet ce soir.', $order->cancel_reason);
        $this->assertSame('Rupture de poulet ce soir.', $order->statusHistories()->latest('id')->first()->note);
        $this->assertSame(['Commande refusée'], $this->notificationTitles($this->client));
        $this->assertStringContainsString('Motif : Rupture de poulet ce soir.', $this->client->notifications()->first()->data['message']);
    }

    public function test_client_cancels_only_while_pending(): void
    {
        $pending = $this->makeOrder();
        $this->workflow->transition($pending, OrderStatus::Cancelled, $this->client);

        $this->assertSame(OrderStatus::Cancelled, $pending->fresh()->status);
        // L'entreprise est prévenue, pas l'auteur de l'annulation.
        $this->assertSame(['Commande annulée'], $this->notificationTitles($this->owner));
        $this->assertSame([], $this->notificationTitles($this->client));

        $accepted = $this->makeOrder(OrderStatus::Accepted);
        $this->assertForbidden($accepted, OrderStatus::Cancelled, $this->client, 'Votre rôle (Client)');
    }

    public function test_admin_cancels_at_any_time_before_delivery_with_a_reason(): void
    {
        $order = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);

        $this->assertForbidden($order, OrderStatus::Cancelled, $this->admin, 'Indiquez le motif');

        $this->workflow->transition($order, OrderStatus::Cancelled, $this->admin, 'Client injoignable.');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        foreach ([$this->client, $this->owner, $this->courier] as $party) {
            $this->assertSame(['Commande annulée'], $this->notificationTitles($party));
        }

        foreach (OrderStatus::cases() as $status) {
            if ($status->isFinal()) {
                $this->assertForbidden($this->makeOrder($status), OrderStatus::Cancelled, $this->admin, 'ne peut plus changer', 'Motif');
            }
        }
    }

    public function test_wrong_role_is_refused(): void
    {
        $pending = $this->makeOrder();
        $this->assertForbidden($pending, OrderStatus::Accepted, $this->client, 'Votre rôle (Client)');
        $this->assertForbidden($pending, OrderStatus::Accepted, $this->courier, 'Votre rôle (Livreur)');
        $this->assertForbidden($pending, OrderStatus::Accepted, $this->admin, 'Votre rôle (Administrateur)');

        $searching = $this->makeOrder(OrderStatus::SearchingCourier);
        $this->assertForbidden($searching, OrderStatus::CourierAssigned, $this->owner, 'Votre rôle (Entreprise)');

        $assigned = $this->makeOrder(OrderStatus::CourierAssigned, ['delivery_id' => $this->courier->id]);
        $this->assertForbidden($assigned, OrderStatus::Delivering, $this->owner, 'Votre rôle (Entreprise)');
        $this->assertForbidden($assigned, OrderStatus::Delivering, $this->admin, 'Votre rôle (Administrateur)');
    }

    public function test_wrong_status_is_refused(): void
    {
        // Pas de saut d'étape ni de retour en arrière.
        $this->assertForbidden($this->makeOrder(), OrderStatus::Preparing, $this->owner, 'Impossible de passer');
        $this->assertForbidden($this->makeOrder(OrderStatus::Accepted), OrderStatus::SearchingCourier, $this->owner, 'Impossible de passer');
        $this->assertForbidden($this->makeOrder(OrderStatus::Preparing), OrderStatus::Accepted, $this->owner, 'Impossible de passer');
        $this->assertForbidden($this->makeOrder(OrderStatus::Pending), OrderStatus::CourierAssigned, $this->courier, 'Impossible de passer');
        $this->assertForbidden(
            $this->makeOrder(OrderStatus::CourierAssigned, ['delivery_id' => $this->courier->id]),
            OrderStatus::Delivered,
            $this->courier,
            'Impossible de passer',
        );
        // Une commande terminée ne bouge plus.
        $this->assertForbidden($this->makeOrder(OrderStatus::Refused), OrderStatus::Accepted, $this->owner, 'ne peut plus changer');
    }

    public function test_other_store_or_other_client_is_refused(): void
    {
        $otherOwner = User::factory()->create(['role' => 'business']);
        \App\Models\Store::factory()->create(['owner_id' => $otherOwner->id]);
        $this->assertForbidden($this->makeOrder(), OrderStatus::Accepted, $otherOwner, 'ne concerne pas votre commerce');

        $otherClient = User::factory()->create(['role' => 'client']);
        $this->assertForbidden($this->makeOrder(), OrderStatus::Cancelled, $otherClient, 'n’est pas la vôtre');
    }

    public function test_courier_must_be_in_the_zone_and_available(): void
    {
        $order = $this->makeOrder(OrderStatus::SearchingCourier);

        $this->assertForbidden($order, OrderStatus::CourierAssigned, $this->farCourier, 'pas dans votre zone');

        $unavailable = $this->makeCourier('Indispo', Neighborhood::firstWhere('name', 'Louis'), available: false);
        $this->assertForbidden($order, OrderStatus::CourierAssigned, $unavailable, 'indisponible');

        $pending = $this->makeCourier('En attente', Neighborhood::firstWhere('name', 'Louis'), state: 'pending');
        $this->assertForbidden($order, OrderStatus::CourierAssigned, $pending, 'doit être validé');
    }

    public function test_only_the_assigned_courier_moves_the_delivery_forward(): void
    {
        $colleague = $this->makeCourier('Collègue', Neighborhood::firstWhere('name', 'Louis'));
        $order = $this->makeOrder(OrderStatus::CourierAssigned, ['delivery_id' => $this->courier->id]);

        $this->assertForbidden($order, OrderStatus::Delivering, $colleague, 'pas assignée à votre compte');

        $this->workflow->transition($order, OrderStatus::Delivering, $this->courier);
        $this->assertSame(OrderStatus::Delivering, $order->fresh()->status);
    }

    public function test_an_order_can_only_be_taken_by_one_courier(): void
    {
        $colleague = $this->makeCourier('Collègue', Neighborhood::firstWhere('name', 'Louis'));
        $order = $this->makeOrder(OrderStatus::SearchingCourier);

        $this->workflow->transition($order, OrderStatus::CourierAssigned, $this->courier);

        // Le second livreur part de la même commande (page restée ouverte) : refusé.
        $this->assertForbidden($order, OrderStatus::CourierAssigned, $colleague, 'déjà été prise par un autre livreur');
        $this->assertSame($this->courier->id, $order->fresh()->delivery_id);
        $this->assertSame(1, $order->statusHistories()->where('status', OrderStatus::CourierAssigned)->count());
    }

    public function test_concurrent_status_change_is_detected(): void
    {
        $order = $this->makeOrder(OrderStatus::SearchingCourier);
        // Un autre livreur a pris la course juste avant (base modifiée hors de ce modèle en mémoire).
        Order::whereKey($order->id)->update(['delivery_id' => $this->farCourier->id]);

        $this->assertForbidden($order, OrderStatus::CourierAssigned, $this->courier, 'déjà été prise');
    }

    public function test_allowed_transitions_depend_on_role_status_and_actor(): void
    {
        $pending = $this->makeOrder();
        $this->assertSame([OrderStatus::Accepted, OrderStatus::Refused], $this->workflow->allowedTransitions($pending, $this->owner));
        $this->assertSame([OrderStatus::Cancelled], $this->workflow->allowedTransitions($pending, $this->client));
        $this->assertSame([OrderStatus::Cancelled], $this->workflow->allowedTransitions($pending, $this->admin));
        $this->assertSame([], $this->workflow->allowedTransitions($pending, $this->courier));

        $searching = $this->makeOrder(OrderStatus::SearchingCourier);
        $this->assertSame([OrderStatus::CourierAssigned], $this->workflow->allowedTransitions($searching, $this->courier));
        $this->assertSame([], $this->workflow->allowedTransitions($searching, $this->farCourier));
        $this->assertSame([], $this->workflow->allowedTransitions($this->makeOrder(OrderStatus::Delivered), $this->admin));
    }

    public function test_status_endpoint_uses_the_workflow_for_every_role(): void
    {
        $order = $this->makeOrder();

        // Transition interdite : message d'erreur clair, commande inchangée.
        $this->actingAs($this->client)
            ->from('/somewhere')
            ->put("/orders/{$order->id}/status", ['status' => 'acceptee'])
            ->assertRedirect('/somewhere')
            ->assertSessionHas('error', 'Votre rôle (Client) ne permet pas de passer cette commande à « Acceptée ».');
        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);

        $this->actingAs($this->owner)
            ->put("/orders/{$order->id}/status", ['status' => 'acceptee'])
            ->assertSessionHas('success', "Commande {$order->reference} : Acceptée.");
        $this->assertSame(OrderStatus::Accepted, $order->fresh()->status);

        $this->actingAs($this->owner)
            ->putJson("/orders/{$order->id}/status", ['status' => 'livree'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Impossible de passer la commande '.$order->reference.' de « Acceptée » à « Livrée ».');

        $this->actingAs($this->owner)->put("/orders/{$order->id}/status", ['status' => 'inconnu'])->assertSessionHasErrors('status');
        auth()->logout();
        $this->put("/orders/{$order->id}/status", ['status' => 'en_preparation'])->assertRedirect(route('login', absolute: false));
    }
}
