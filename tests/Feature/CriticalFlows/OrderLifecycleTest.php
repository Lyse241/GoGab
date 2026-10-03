<?php

namespace Tests\Feature\CriticalFlows;

use App\Enums\OrderStatus;
use App\Models\Neighborhood;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Parcours critique 4 — une commande de bout en bout, par les routes HTTP de chaque espace :
 * création → acceptation → préparation → annonce → prise par un livreur → récupération →
 * arrivée → livraison. À chaque étape : le bon statut, les bonnes notifications (et personne
 * d'autre), puis l'historique complet.
 */
class OrderLifecycleTest extends TestCase
{
    use BuildsOrders;
    use CriticalFlowHelpers;
    use RefreshDatabase;

    private const NOBODY = ['client' => [], 'business' => [], 'courier' => [], 'far_courier' => [], 'admin' => []];

    /**
     * Exécute une étape et vérifie le statut obtenu et les notifications reçues par chacun.
     *
     * @param  array<string, list<string>>  $notified  personnes notifiées => titres (les autres : rien)
     */
    private function step(string $label, callable $action, OrderStatus $expected, array $notified): void
    {
        $people = [
            'client' => $this->client,
            'business' => $this->owner,
            'courier' => $this->courier,
            'far_courier' => $this->farCourier,
            'admin' => $this->admin,
        ];

        $received = $this->notificationsDuring($people, $action);

        $this->assertSame($expected, Order::sole()->status, "Étape « {$label} » : mauvais statut.");
        $this->assertSame([...self::NOBODY, ...$notified], $received, "Étape « {$label} » : mauvaises notifications.");
    }

    public function test_an_order_goes_from_creation_to_delivery_with_notifications_and_history(): void
    {
        $this->setUpOrderWorld();
        config(['gogab.delivery_fee' => 1000]);
        $poulet = $this->store->products()->create(['name' => 'Poulet nyembwe', 'price' => 4500]);
        $status = fn (string $to, array $extra = []) => fn () => $this->put(route('orders.status.update', Order::sole()), ['status' => $to, ...$extra])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        // 1. Création par le client (paiement à la livraison, il remet 15 000 FCFA).
        $received = $this->notificationsDuring(['business' => $this->owner, 'client' => $this->client, 'courier' => $this->courier], fn () => $this->actingAs($this->client)
            ->post('/orders', [
                'store_id' => $this->store->id,
                'neighborhood_id' => Neighborhood::firstWhere('name', 'Glass')->id,
                'address_landmarks' => 'Près de la pharmacie, portail bleu',
                'payment_method' => 'cash',
                'cash_given' => 15000,
                'client_note' => 'Sans piment',
                'items' => [['product_id' => $poulet->id, 'quantity' => 2, 'price' => 1]],
            ])
            ->assertSessionHasNoErrors());
        $order = Order::sole();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('10000.00', $order->total_price); // 2 × 4 500 + 1 000 de frais, prix du serveur
        $this->assertSame(5000.0, $order->change_due);
        $this->assertSame(['business' => ["Nouvelle commande {$order->reference}"], 'client' => [], 'courier' => []], $received);

        // 2. Acceptation, 3. préparation, 4. annonce : entreprise.
        $this->actingAs($this->owner);
        $this->step('acceptation', $status('acceptee'), OrderStatus::Accepted, ['client' => ['Commande acceptée']]);
        $this->step('préparation', $status('en_preparation'), OrderStatus::Preparing, ['client' => ['Commande en préparation']]);
        $this->step('annonce', $status('en_recherche_livreur'), OrderStatus::SearchingCourier, [
            'client' => ['Recherche d’un livreur'],
            'courier' => ['Nouvelle course disponible'], // livreur de la zone ; pas celui d'une autre zone
        ]);

        // L'offre est visible du livreur de la zone, avec le montant remis et la monnaie à prévoir.
        $this->actingAs($this->courier)
            ->get('/delivery/offers')
            ->assertInertia(fn (Assert $page) => $page->where('offers.0.id', $order->id)->where('offers.0.change_due', 5000));
        $this->actingAs($this->farCourier)->get('/delivery/offers')->assertInertia(fn (Assert $page) => $page->has('offers', 0));

        // 5. Prise par le livreur.
        $this->step('prise par un livreur', fn () => $this->actingAs($this->courier)
            ->post(route('delivery.orders.accept', $order))
            ->assertRedirect(route('delivery.current')), OrderStatus::CourierAssigned, [
                'client' => ['Livreur trouvé'],
                'business' => ['Livreur assigné'],
            ]);
        $this->assertSame($this->courier->id, $order->fresh()->delivery_id);

        // 6. Récupération, 7. arrivée, 8. livraison (encaissement confirmé) : livreur.
        $this->actingAs($this->courier);
        $this->step('récupération', $status('en_livraison'), OrderStatus::Delivering, [
            'client' => ['Commande récupérée'],
            'business' => ['Commande récupérée'],
        ]);
        $this->step('arrivée', $status('arrive'), OrderStatus::Arrived, ['client' => ['Votre livreur est arrivé']]);
        $this->step('livraison', $status('livree', ['cash_collected' => true]), OrderStatus::Delivered, [
            'client' => ['Commande livrée'],
            'business' => ['Commande livrée'],
        ]);
        $this->assertNotNull($order->fresh()->cash_collected_at);

        // Historique : une entrée par étape, dans l'ordre, avec son auteur.
        $history = $order->statusHistories()->get();
        $this->assertSame(
            ['en_attente', 'acceptee', 'en_preparation', 'en_recherche_livreur', 'livreur_assigne', 'en_livraison', 'arrive', 'livree'],
            $history->pluck('status')->map->value->all(),
        );
        $this->assertSame(
            [$this->client->id, $this->owner->id, $this->owner->id, $this->owner->id, $this->courier->id, $this->courier->id, $this->courier->id, $this->courier->id],
            $history->pluck('changed_by')->all(),
        );

        // Le client voit la timeline complète ; l'historique du livreur compte la course et son gain.
        $this->actingAs($this->client)
            ->get(route('orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->where('order.status', 'livree')
                ->where('order.is_final', true)
                // 8 étapes : les 7 premières faites, « Livrée » est l'étape finale (courante).
                ->where('order.timeline', fn ($timeline) => collect($timeline)->pluck('state')->all() === [...array_fill(0, 7, 'done'), 'current'])
                ->where('order.timeline.7.label', 'Livrée'));
        $this->actingAs($this->courier)
            ->get('/delivery/history')
            ->assertInertia(fn (Assert $page) => $page->where('deliveries.data.0.id', $order->id)->where('earnings.today', 1000));
    }
}
