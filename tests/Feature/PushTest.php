<?php

namespace Tests\Feature;

use App\Contracts\PushTransport;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifier;
use App\Services\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Feature\Orders\BuildsOrders;
use Tests\TestCase;

/**
 * Faux transport : enregistre les envois au lieu d'appeler les services de Google / Mozilla / Apple.
 */
class FakePushTransport implements PushTransport
{
    /** @var list<array{subscription: int, payload: array<string, mixed>}> */
    public array $sent = [];

    public bool|null|RuntimeException $result = true;

    public function send(PushSubscription $subscription, array $payload): ?bool
    {
        if ($this->result instanceof RuntimeException) {
            throw $this->result;
        }
        $this->sent[] = ['subscription' => $subscription->id, 'payload' => $payload];

        return $this->result;
    }
}

class PushTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    private FakePushTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gogab.push.public_key' => 'BPublicKeyForTests', 'gogab.push.private_key' => 'privateKeyForTests']);
        $this->transport = new FakePushTransport;
        $this->app->instance(PushTransport::class, $this->transport);
    }

    private function subscription(string $endpoint = 'https://push.example.com/abc'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key']];
    }

    public function test_a_user_subscribes_and_unsubscribes_a_device(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/push/subscription', $this->subscription())->assertCreated();
        $this->assertSame(1, PushSubscription::where('user_id', $user->id)->count());

        // Même appareil, même abonnement : mis à jour, pas dupliqué.
        $this->actingAs($user)->postJson('/push/subscription', $this->subscription())->assertCreated();
        $this->assertSame(1, PushSubscription::count());

        $this->actingAs($user)->deleteJson('/push/subscription', ['endpoint' => 'https://push.example.com/abc'])->assertOk();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_a_shared_device_belongs_to_the_last_account_and_nobody_unsubscribes_another(): void
    {
        [$first, $second] = User::factory()->count(2)->create();

        $this->actingAs($first)->postJson('/push/subscription', $this->subscription());
        $this->actingAs($second)->postJson('/push/subscription', $this->subscription());
        $this->assertSame($second->id, PushSubscription::sole()->user_id);

        $this->actingAs($first)->deleteJson('/push/subscription', ['endpoint' => 'https://push.example.com/abc']);
        $this->assertSame(1, PushSubscription::count(), 'le premier compte ne peut pas retirer l’abonnement du second');
    }

    public function test_subscription_is_validated_and_needs_a_login_and_configured_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/push/subscription', $this->subscription('http://insecure.example.com/x'))->assertStatus(422);
        $this->actingAs($user)->postJson('/push/subscription', ['endpoint' => 'https://push.example.com/abc'])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->postJson('/push/subscription', $this->subscription())->assertUnauthorized();

        config(['gogab.push.public_key' => null]);
        $this->actingAs($user)->postJson('/push/subscription', $this->subscription())->assertNotFound();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_the_public_key_is_shared_only_when_push_is_configured(): void
    {
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($user)->get('/notifications')->assertInertia(fn ($page) => $page->where('push.public_key', 'BPublicKeyForTests'));

        config(['gogab.push.private_key' => null]);
        $this->actingAs($user)->get('/notifications')->assertInertia(fn ($page) => $page->where('push.public_key', null));
    }

    public function test_a_notification_is_pushed_to_the_devices_of_its_recipient_only(): void
    {
        [$recipient, $other] = User::factory()->count(2)->create();
        $this->actingAs($recipient)->postJson('/push/subscription', $this->subscription('https://push.example.com/phone'));
        $this->actingAs($recipient)->postJson('/push/subscription', $this->subscription('https://push.example.com/laptop'));
        $this->actingAs($other)->postJson('/push/subscription', $this->subscription('https://push.example.com/other'));

        Notifier::send($recipient, 'Commande acceptée', 'Chez Maman Ngoye prépare votre commande.', url('/orders/7'));

        $this->assertCount(2, $this->transport->sent);
        $this->assertSame(
            ['title' => 'Commande acceptée', 'body' => 'Chez Maman Ngoye prépare votre commande.', 'url' => '/orders/7'],
            $this->transport->sent[0]['payload'],
        );
        // La notification in-app existe toujours.
        $this->assertCount(1, $recipient->notifications);
    }

    public function test_expired_subscriptions_are_removed_and_temporary_failures_are_kept(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push/subscription', $this->subscription());

        $this->transport->result = null;
        Notifier::send($user, 'Test', 'Message');
        $this->assertSame(1, PushSubscription::count(), 'échec passager : on garde l’abonnement');

        $this->transport->result = false;
        Notifier::send($user, 'Test', 'Message');
        $this->assertSame(0, PushSubscription::count(), 'abonnement expiré (410) : supprimé');
    }

    public function test_a_push_failure_never_breaks_the_notification(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/push/subscription', $this->subscription());

        $this->transport->result = new RuntimeException('réseau coupé');

        $this->assertSame(1, Notifier::send($user, 'Test', 'Message'));
        $this->assertCount(1, $user->notifications);
    }

    public function test_nothing_is_pushed_without_configured_keys(): void
    {
        $user = User::factory()->create();
        PushSubscription::create([
            'user_id' => $user->id, 'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.com/abc'),
            'endpoint' => 'https://push.example.com/abc', 'p256dh_key' => 'k', 'auth_key' => 'a',
        ]);
        config(['gogab.push.public_key' => null]);

        Notifier::send($user, 'Test', 'Message');

        $this->assertSame([], $this->transport->sent);
    }

    public function test_the_business_gets_a_push_when_an_order_is_placed(): void
    {
        $this->setUpOrderWorld();
        $this->actingAs($this->owner)->postJson('/push/subscription', $this->subscription());
        $product = $this->store->products()->create(['name' => 'Poulet DG', 'price' => 6500]);

        app(OrderWorkflow::class)->place($this->client, [
            'store_id' => $this->store->id,
            'neighborhood_id' => \App\Models\Neighborhood::firstWhere('name', 'Glass')->id,
            'address_landmarks' => 'Près du marché',
            'subtotal' => 6500, 'delivery_fee' => 1000, 'total_price' => 7500,
            'payment_method' => 'cash', 'cash_given' => 10000,
        ], [['product_id' => $product->id, 'quantity' => 1, 'price' => 6500]]);

        $this->assertCount(1, $this->transport->sent);
        $this->assertStringStartsWith('Nouvelle commande GG-', $this->transport->sent[0]['payload']['title']);
        $this->assertStringStartsWith('/business/orders/', $this->transport->sent[0]['payload']['url']);
    }
}
