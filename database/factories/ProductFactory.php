<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'menu_section' => null,
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'description' => null,
            'price' => fake()->numberBetween(5, 100) * 100,
            'image' => null,
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }

    public function inSection(string $section): static
    {
        return $this->state(fn () => ['menu_section' => $section]);
    }
}
