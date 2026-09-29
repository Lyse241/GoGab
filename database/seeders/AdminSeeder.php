<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Compte administrateur (mot de passe : "password").
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gogab.ga'],
            [
                'name' => 'Administrateur Gogab',
                'phone' => '074 00 00 01',
                'role' => Role::Admin,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'account_status' => AccountStatus::Approved,
                'approved_at' => now(),
            ],
        );
    }
}
