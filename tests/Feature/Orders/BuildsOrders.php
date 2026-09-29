<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderStatus;
use App\Enums\VehicleType;
use App\Models\Neighborhood;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;

/**
 * Décor des tests de commande : un commerce (zone Centre) avec son entreprise, un client,
 * un livreur de la zone, un livreur d'une autre zone, un admin.
 */
trait BuildsOrders
{
    protected User $owner;

    protected Store $store;

    protected User $client;

    protected User $courier;

    protected User $farCourier;

    protected User $admin;

    protected function setUpOrderWorld(): void
    {
        $louis = Neighborhood::create(['name' => 'Louis', 'zone' => 'Centre']);
        $glass = Neighborhood::create(['name' => 'Glass', 'zone' => 'Centre']);
        $akanda = Neighborhood::create(['name' => 'Akanda', 'zone' => 'Nord']);

        $this->owner = User::factory()->create(['role' => 'business', 'name' => 'Maman Ngoye']);
        $this->store = Store::factory()->create(['owner_id' => $this->owner->id, 'name' => 'Chez Maman Ngoye', 'neighborhood_id' => $louis->id]);
        $this->client = User::factory()->create(['role' => 'client', 'name' => 'Marie Ndong', 'phone' => '066 20 00 01']);
        $this->courier = $this->makeCourier('Jean Livreur', $glass);
        $this->farCourier = $this->makeCourier('Paul Loin', $akanda);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    protected function makeCourier(string $name, Neighborhood $base, bool $available = true, string $state = 'approved'): User
    {
        $courier = User::factory()->{$state}()->create(['role' => 'delivery', 'name' => $name]);
        $courier->deliveryProfile()->create([
            'vehicle_type' => VehicleType::Moto,
            'base_neighborhood_id' => $base->id,
            'is_available' => $available,
        ]);

        return $courier;
    }

    protected function makeOrder(OrderStatus $status = OrderStatus::Pending, array $attributes = []): Order
    {
        $product = $this->store->products()->firstOrCreate(['name' => 'Poulet nyembwe'], ['price' => 4500]);

        $order = Order::create([
            'store_id' => $this->store->id,
            'client_id' => $this->client->id,
            'neighborhood_id' => Neighborhood::firstWhere('name', 'Glass')->id,
            'address_landmarks' => 'Près de la pharmacie, portail bleu',
            'subtotal' => 9000,
            'total_price' => 9000,
            'payment_method' => 'cash',
            'cash_given' => 10000,
            'status' => $status,
            ...$attributes,
        ]);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 2, 'price' => 4500]);

        return $order;
    }

    /**
     * Titres des notifications reçues par un utilisateur, triés (les identifiants sont des UUID :
     * l'ordre d'envoi n'est pas fiable dans une même seconde).
     *
     * @return list<string>
     */
    protected function notificationTitles(User $user): array
    {
        return $user->notifications()->get()->pluck('data.title')->sort()->values()->all();
    }
}
