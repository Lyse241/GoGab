<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Comptes de démonstration livreurs et clients (mot de passe : "password").
     * L'administrateur est créé par AdminSeeder. Nécessite NeighborhoodSeeder.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Livreur Un', 'email' => 'livreur1@gogab.ga', 'phone' => '077 10 00 01', 'role' => Role::Delivery, 'neighborhood' => 'Louis'],
            ['name' => 'Livreur Deux', 'email' => 'livreur2@gogab.ga', 'phone' => '077 10 00 02', 'role' => Role::Delivery, 'neighborhood' => 'Nzeng-Ayong'],
            ['name' => 'Client Un', 'email' => 'client1@gogab.ga', 'phone' => '066 20 00 01', 'role' => Role::Client, 'neighborhood' => 'Glass'],
            ['name' => 'Client Deux', 'email' => 'client2@gogab.ga', 'phone' => '066 20 00 02', 'role' => Role::Client, 'neighborhood' => 'Akanda'],
            ['name' => 'Client Trois', 'email' => 'client3@gogab.ga', 'phone' => '066 20 00 03', 'role' => Role::Client, 'neighborhood' => 'Owendo'],
        ];

        $neighborhoods = Neighborhood::pluck('id', 'name');

        foreach ($users as $user) {
            $neighborhood = $user['neighborhood'];
            unset($user['neighborhood']);

            $user = User::updateOrCreate(
                ['email' => $user['email']],
                [
                    ...$user,
                    'neighborhood_id' => $neighborhoods[$neighborhood],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'account_status' => AccountStatus::Approved,
                    'approved_at' => now(),
                ],
            );

            if ($user->isDelivery()) {
                $user->deliveryProfile()->updateOrCreate([], [
                    'vehicle_type' => VehicleType::Moto,
                    'base_neighborhood_id' => $neighborhoods[$neighborhood],
                    'is_available' => true,
                ]);
            }
        }
    }
}
