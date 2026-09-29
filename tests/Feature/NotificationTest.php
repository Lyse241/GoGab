<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'client']);
    }

    // --- Notifier ---

    public function test_notifier_sends_a_database_notification_to_one_user(): void
    {
        $sent = Notifier::send($this->user, 'Commande acceptée', 'Votre commande est en préparation.', '/orders/12', 'success');

        $this->assertSame(1, $sent);
        $notification = $this->user->notifications()->sole();
        $this->assertSame(AppNotification::class, $notification->type);
        $this->assertSame([
            'title' => 'Commande acceptée',
            'message' => 'Votre commande est en préparation.',
            'url' => '/orders/12',
            'type' => 'success',
        ], $notification->data);
        $this->assertNull($notification->read_at);
    }

    public function test_notifier_accepts_a_collection_and_ignores_nulls_and_duplicates(): void
    {
        $other = User::factory()->create();

        $sent = Notifier::send(collect([$this->user, null, $other, $this->user]), 'Info', 'Message');

        $this->assertSame(2, $sent);
        $this->assertSame(1, $this->user->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
        $this->assertSame(0, Notifier::send(collect(), 'Info', 'Personne'));
        $this->assertSame(0, Notifier::send(null, 'Info', 'Personne'));
    }

    public function test_notifier_stores_internal_links_as_relative_paths(): void
    {
        config(['app.url' => 'http://localhost']);

        Notifier::send($this->user, 'A', 'B', 'http://localhost/orders/5?tab=suivi');
        Notifier::send($this->user, 'A', 'B', 'https://exemple.ga/page');

        $urls = $this->user->notifications()->get()->pluck('data.url')->sort()->values()->all();
        $this->assertSame(['/orders/5?tab=suivi', 'https://exemple.ga/page'], $urls);
    }

    public function test_links_built_on_another_host_than_app_url_stay_relative(): void
    {
        config(['app.url' => 'http://localhost']);
        // Requête servie sur 127.0.0.1 (php artisan serve) : route() utilise cet hôte.
        \Illuminate\Support\Facades\URL::forceRootUrl('http://127.0.0.1:8000');

        Notifier::send($this->user, 'A', 'B', route('orders.show', 12));

        $this->assertSame('/orders/12', $this->user->notifications()->sole()->data['url']);
        \Illuminate\Support\Facades\URL::forceRootUrl(null);
    }

    public function test_notifier_rejects_an_unknown_type(): void
    {
        Notification::fake();

        $this->expectException(InvalidArgumentException::class);
        Notifier::send($this->user, 'A', 'B', null, 'danger');
    }

    // --- JSON de la cloche ---

    public function test_unread_endpoint_returns_count_and_ten_latest(): void
    {
        foreach (range(1, 12) as $index) {
            Notifier::send($this->user, "Titre {$index}", "Message {$index}");
            $this->travel(1)->minutes();
        }
        $this->user->notifications()->latest()->first()->markAsRead();
        Notifier::send(User::factory()->create(), 'Autre', 'Pas pour moi');

        $this->actingAs($this->user)
            ->getJson('/notifications/unread')
            ->assertOk()
            ->assertJsonPath('unread_count', 11)
            ->assertJsonCount(10, 'notifications')
            ->assertJsonPath('notifications.0.title', 'Titre 12')
            ->assertJsonPath('notifications.0.read', true)
            ->assertJsonPath('notifications.1.title', 'Titre 11')
            ->assertJsonPath('notifications.1.read', false)
            ->assertJsonStructure(['notifications' => [['id', 'title', 'message', 'url', 'type', 'read', 'created_at']]]);
    }

    public function test_guests_cannot_read_notifications(): void
    {
        $this->getJson('/notifications/unread')->assertUnauthorized();
        $this->get('/notifications')->assertRedirect(route('login', absolute: false));
    }

    // --- Lecture ---

    public function test_a_notification_can_be_marked_as_read(): void
    {
        Notifier::send($this->user, 'A', 'B');
        Notifier::send($this->user, 'C', 'D');
        $notification = $this->user->notifications()->first();

        $this->actingAs($this->user)
            ->postJson("/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJson(['unread_count' => 1]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_someone_elses_notification_cannot_be_marked_as_read(): void
    {
        $other = User::factory()->create();
        Notifier::send($other, 'A', 'B');
        $notification = $other->notifications()->sole();

        $this->actingAs($this->user)
            ->postJson("/notifications/{$notification->id}/read")
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_all_notifications_can_be_marked_as_read(): void
    {
        $other = User::factory()->create();
        Notifier::send([$this->user, $other], 'A', 'B');
        Notifier::send($this->user, 'C', 'D');

        $this->actingAs($this->user)
            ->postJson('/notifications/read-all')
            ->assertOk()
            ->assertJson(['unread_count' => 0]);

        $this->assertSame(0, $this->user->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count()); // pas touché

        // Sans JSON (formulaire classique) : retour à la page avec un message.
        Notifier::send($this->user, 'E', 'F');
        $this->actingAs($this->user)
            ->from('/notifications')
            ->post('/notifications/read-all')
            ->assertRedirect('/notifications')
            ->assertSessionHas('success');
    }

    // --- Page ---

    public function test_notifications_page_lists_and_filters(): void
    {
        Notifier::send($this->user, 'Lue', 'Message lu');
        $this->user->notifications()->sole()->markAsRead();
        Notifier::send($this->user, 'Non lue', 'Message non lu');

        $this->actingAs($this->user)
            ->get('/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Index')
                ->where('filter', 'all')
                ->where('counts', ['all' => 2, 'unread' => 1])
                ->has('notifications.data', 2));

        $this->actingAs($this->user)
            ->get('/notifications?filter=unread')
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 1)
                ->where('notifications.data.0.title', 'Non lue'));

        $this->actingAs($this->user)
            ->get('/notifications?filter=read')
            ->assertInertia(fn (Assert $page) => $page
                ->has('notifications.data', 1)
                ->where('notifications.data.0.title', 'Lue'));

        $this->actingAs($this->user)
            ->get('/notifications?filter=nimporte')
            ->assertSessionHasErrors('filter');
    }

    public function test_unread_count_is_shared_with_every_page(): void
    {
        Notifier::send($this->user, 'A', 'B');
        Notifier::send($this->user, 'C', 'D');

        $this->actingAs($this->user)
            ->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('auth.unread_notifications', 2));

        auth()->logout();

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('auth.unread_notifications', null));
    }

    // --- Commande artisan ---

    public function test_notify_test_command_sends_a_demo_notification(): void
    {
        $this->artisan('gogab:notify-test', ['email' => $this->user->email, '--type' => 'success'])
            ->expectsOutputToContain('envoyée')
            ->assertSuccessful();

        $notification = $this->user->notifications()->sole();
        $this->assertSame('Notification de test', $notification->data['title']);
        $this->assertSame('success', $notification->data['type']);
        $this->assertSame('/notifications', $notification->data['url']);
    }

    public function test_notify_test_command_fails_for_unknown_email_or_type(): void
    {
        $this->artisan('gogab:notify-test', ['email' => 'inconnu@gogab.ga'])->assertFailed();
        $this->artisan('gogab:notify-test', ['email' => $this->user->email, '--type' => 'danger'])->assertFailed();

        $this->assertSame(0, $this->user->notifications()->count());
    }
}
