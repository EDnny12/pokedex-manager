<?php

namespace Database\Factories;

use App\Models\PokemonCollectionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PokemonCollectionItem>
 */
class PokemonCollectionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pokemon_id' => fake()->unique()->numberBetween(1, 1025),
            'nickname' => fake()->optional()->firstName(),
            'notes' => fake()->optional()->sentence(),
            'is_favorite' => fake()->boolean(25),
        ];
    }
}
