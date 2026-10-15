<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use BuildsOrders;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrderWorld();
    }

    private function delivered()
    {
        return $this->makeOrder(OrderStatus::Delivered, ['delivery_id' => $this->courier->id]);
    }

    public function test_client_reviews_a_delivered_order_once(): void
    {
        $order = $this->delivered();

        $this->actingAs($this->client)->get("/orders/{$order->id}")->assertInertia(fn ($page) => $page
            ->where('can_review', true)->where('review', null));

        $this->actingAs($this->client)
            ->post("/orders/{$order->id}/review", ['store_rating' => 5, 'courier_rating' => 4, 'comment' => '  Délicieux  '])
            ->assertSessionHasNoErrors();

        $review = Review::sole();
        $this->assertSame(5, $review->store_rating);
        $this->assertSame(4, $review->courier_rating);
        $this->assertSame('Délicieux', $review->comment);
        $this->assertSame($this->courier->id, $review->courier_id);
        $this->assertContains("Nouvel avis sur {$order->reference}", $this->notificationTitles($this->owner));
        $this->assertContains("Nouvel avis sur {$order->reference}", $this->notificationTitles($this->courier));

        // Une seule fois par commande.
        $this->actingAs($this->client)->post("/orders/{$order->id}/review", ['store_rating' => 1, 'courier_rating' => 1])->assertForbidden();
        $this->assertSame(1, Review::count());
        $this->actingAs($this->client)->get("/orders/{$order->id}")->assertInertia(fn ($page) => $page
            ->where('can_review', false)->where('review.store_rating', 5));
    }

    public function test_ratings_are_validated(): void
    {
        $order = $this->delivered();

        $this->actingAs($this->client)->post("/orders/{$order->id}/review", ['store_rating' => 6, 'courier_rating' => 0])
            ->assertSessionHasErrors(['store_rating', 'courier_rating']);
        $this->actingAs($this->client)->post("/orders/{$order->id}/review", [])
            ->assertSessionHasErrors(['store_rating', 'courier_rating']);
        $this->assertSame(0, Review::count());
    }

    public function test_only_the_client_of_a_delivered_order_can_review(): void
    {
        $inProgress = $this->makeOrder(OrderStatus::Delivering, ['delivery_id' => $this->courier->id]);
        $delivered = $this->delivered();
        $other = \App\Models\User::factory()->create(['role' => 'client']);

        $this->actingAs($this->client)->post("/orders/{$inProgress->id}/review", ['store_rating' => 5, 'courier_rating' => 5])->assertForbidden();
        $this->actingAs($other)->post("/orders/{$delivered->id}/review", ['store_rating' => 5, 'courier_rating' => 5])->assertForbidden();
        $this->actingAs($this->owner)->post("/orders/{$delivered->id}/review", ['store_rating' => 5, 'courier_rating' => 5])
            ->assertRedirect(route('business.dashboard'));

        $this->assertSame(0, Review::count());
    }

    public function test_store_rating_average_is_shown_on_home_and_store_page(): void
    {
        $first = $this->delivered();
        $second = $this->delivered();
        $this->actingAs($this->client)->post("/orders/{$first->id}/review", ['store_rating' => 5, 'courier_rating' => 5]);
        $this->actingAs($this->client)->post("/orders/{$second->id}/review", ['store_rating' => 4, 'courier_rating' => 3]);

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('stores.0.rating', 4.5)->where('stores.0.reviews_count', 2));
        $this->get("/stores/{$this->store->id}")->assertInertia(fn ($page) => $page
            ->where('store.rating', 4.5)->where('store.reviews_count', 2));
    }

    public function test_business_sees_its_reviews_and_hidden_comments_are_masked(): void
    {
        $first = $this->delivered();
        $second = $this->delivered();
        $this->actingAs($this->client)->post("/orders/{$first->id}/review", ['store_rating' => 5, 'courier_rating' => 5, 'comment' => 'Super']);
        $this->actingAs($this->client)->post("/orders/{$second->id}/review", ['store_rating' => 1, 'courier_rating' => 2, 'comment' => 'Insulte']);

        $abusive = Review::where('comment', 'Insulte')->first();
        $this->actingAs($this->admin)->patch("/admin/reviews/{$abusive->id}/visibility", ['hidden' => true])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->get('/business/reviews')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Business/Reviews/Index')
            ->where('summary.average', 3)
            ->where('summary.count', 2)
            ->where('reviews.data.0.comment', null)
            ->where('reviews.data.0.comment_hidden', true)
            ->where('reviews.data.0.client', 'Marie')
            ->where('reviews.data.1.comment', 'Super'));

        // Le commentaire réaffiché revient ; la note a toujours compté.
        $this->actingAs($this->admin)->patch("/admin/reviews/{$abusive->id}/visibility", ['hidden' => false]);
        $this->assertNull($abusive->fresh()->hidden_at);
    }

    public function test_only_admins_moderate_reviews(): void
    {
        $order = $this->delivered();
        $this->actingAs($this->client)->post("/orders/{$order->id}/review", ['store_rating' => 3, 'courier_rating' => 3, 'comment' => 'Moyen']);
        $review = Review::sole();

        foreach ([$this->owner, $this->courier, $this->client] as $user) {
            $this->actingAs($user)->patch("/admin/reviews/{$review->id}/visibility", ['hidden' => true])->assertRedirect(route($user->homeRoute()));
        }
        $this->assertNull($review->fresh()->hidden_at);

        $this->actingAs($this->admin)->get('/admin/reviews')->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Reviews/Index')->has('reviews.data', 1));
    }

    public function test_courier_profile_shows_the_average_of_his_ratings(): void
    {
        $this->actingAs($this->courier)->get('/delivery/profile')->assertInertia(fn ($page) => $page
            ->where('rating.average', null)->where('rating.count', 0));

        foreach ([5, 4] as $note) {
            $order = $this->delivered();
            $this->actingAs($this->client)->post("/orders/{$order->id}/review", ['store_rating' => 5, 'courier_rating' => $note]);
        }

        $this->actingAs($this->courier)->get('/delivery/profile')->assertInertia(fn ($page) => $page
            ->where('rating.average', 4.5)->where('rating.count', 2));
    }

    public function test_a_store_without_review_has_no_rating(): void
    {
        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('stores.0.rating', null)->where('stores.0.reviews_count', 0));
    }
}
