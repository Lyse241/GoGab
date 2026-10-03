<?php

namespace Tests\Feature\CriticalFlows;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Enums\VehicleType;
use App\Models\Category;
use App\Models\Neighborhood;
use App\Models\User;
use App\Services\StoreHours;
use Illuminate\Http\UploadedFile;

/**
 * Outils communs aux tests des parcours critiques : formulaires d'inscription complets et
 * relevé des notifications reçues pendant une étape.
 */
trait CriticalFlowHelpers
{
    /**
     * @return array<string, mixed>
     */
    protected function clientRegistration(Neighborhood $neighborhood, array $overrides = []): array
    {
        return [
            'name' => 'Awa Mintsa',
            'phone' => '066 31 32 33',
            'email' => 'awa@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'neighborhood_id' => $neighborhood->id,
            'address_landmarks' => 'Près du marché de Glass, portail rouge',
            ...$overrides,
        ];
    }

    /**
     * Livreur à moto avec ses 7 documents obligatoires.
     *
     * @return array<string, mixed>
     */
    protected function courierRegistration(Neighborhood $home, Neighborhood $base, array $overrides = []): array
    {
        $documents = [];
        foreach (DocumentType::requiredFor(Role::Delivery, VehicleType::Moto) as $type) {
            $documents[$type->value] = $type->isPhoto()
                ? UploadedFile::fake()->image("{$type->value}.jpg", 800, 600)
                : $this->fakePdf("{$type->value}.pdf", 50);
        }

        return [
            'name' => 'Paul Obame',
            'phone' => '077 55 44 33',
            'email' => 'paul@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'neighborhood_id' => $home->id,
            'address_landmarks' => 'Derrière la station Total, maison jaune',
            'vehicle_type' => VehicleType::Moto->value,
            'vehicle_brand' => 'Yamaha Crypton',
            'plate_number' => 'ga-1234-lbv',
            'license_number' => 'P-0456789',
            'base_neighborhood_id' => $base->id,
            'documents' => $documents,
            ...$overrides,
        ];
    }

    /**
     * Entreprise avec son commerce, ses horaires et ses 3 documents obligatoires.
     *
     * @return array<string, mixed>
     */
    protected function businessRegistration(Neighborhood $neighborhood, Category $category, array $overrides = []): array
    {
        return [
            'name' => 'Marie Ngoye',
            'phone' => '077 20 30 40',
            'email' => 'marie@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'store_name' => 'Chez Tante Marie',
            'category_id' => $category->id,
            'description' => 'Cuisine gabonaise maison.',
            'store_phone' => '+241 74 20 30 40',
            'neighborhood_id' => $neighborhood->id,
            'address_landmarks' => 'Face à la pharmacie, bâtiment bleu',
            'opening_hours' => StoreHours::everyDay('00:00', '00:00'),
            'documents' => [
                'business_registration' => $this->fakePdf('rccm.pdf', 50),
                'tax_id' => UploadedFile::fake()->image('nif.jpg'),
                'id_card' => $this->fakePdf('cin.pdf', 50),
            ],
            ...$overrides,
        ];
    }

    /**
     * Exécute une étape et renvoie, pour chaque personne suivie, les titres des notifications
     * reçues pendant cette étape (triés).
     *
     * @param  array<string, User>  $people  libellé => utilisateur
     * @return array<string, list<string>>
     */
    protected function notificationsDuring(array $people, callable $step): array
    {
        $before = array_map(fn (User $user) => $user->notifications()->pluck('id')->all(), $people);

        $step();

        $received = [];
        foreach ($people as $key => $user) {
            $received[$key] = $user->notifications()
                ->whereNotIn('id', $before[$key])
                ->get()
                ->pluck('data.title')
                ->sort()
                ->values()
                ->all();
        }

        return $received;
    }
}
