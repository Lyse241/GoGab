<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Store;
use App\Services\StoreHours;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'category_id' => Category::factory(),
            'is_open' => true,
            'is_active' => true,
        ];
    }

    /**
     * Par défaut, ouvert 24 h/24 tous les jours : les tests qui ne portent pas sur les horaires
     * ne dépendent pas de l'heure à laquelle ils tournent.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Store $store) {
            if (! $store->openingHours()->exists()) {
                StoreHours::sync($store, StoreHours::everyDay('00:00', '00:00'));
            }
        });
    }

    /**
     * Rattache la boutique à la catégorie de ce nom (créée si besoin).
     */
    public function inCategory(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id,
        ]);
    }

    /**
     * Horaires précis (ex. StoreHours::everyDay('08:00', '22:00', [7])).
     *
     * @param  list<array<string, mixed>>  $days
     */
    public function withHours(array $days): static
    {
        return $this->afterCreating(function (Store $store) use ($days) {
            $store->openingHours()->delete();
            StoreHours::sync($store, $days);
        });
    }

    public function withoutHours(): static
    {
        return $this->afterCreating(fn (Store $store) => $store->openingHours()->delete());
    }

    /**
     * Interrupteur de fermeture temporaire activé.
     */
    public function temporarilyClosed(): static
    {
        return $this->state(fn (array $attributes) => ['is_open' => false]);
    }
}
