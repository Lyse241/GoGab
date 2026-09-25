<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Comptes de démonstration (mot de passe : "password").
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Administrateur Gogab', 'email' => 'admin@gogab.ga', 'phone' => '074 00 00 01', 'role' => User::ROLE_ADMIN],
            ['name' => 'Livreur Un', 'email' => 'livreur1@gogab.ga', 'phone' => '077 10 00 01', 'role' => User::ROLE_DELIVERY],
            ['name' => 'Livreur Deux', 'email' => 'livreur2@gogab.ga', 'phone' => '077 10 00 02', 'role' => User::ROLE_DELIVERY],
            ['name' => 'Client Un', 'email' => 'client1@gogab.ga', 'phone' => '066 20 00 01', 'role' => User::ROLE_CLIENT],
            ['name' => 'Client Deux', 'email' => 'client2@gogab.ga', 'phone' => '066 20 00 02', 'role' => User::ROLE_CLIENT],
            ['name' => 'Client Trois', 'email' => 'client3@gogab.ga', 'phone' => '066 20 00 03', 'role' => User::ROLE_CLIENT],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [...$user, 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );
        }
    }
}
