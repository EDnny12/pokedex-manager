<?php

namespace Database\Factories;

use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantAction>
 */
class AssistantActionFactory extends Factory
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
            'conversation_id' => AssistantConversation::factory(),
            'type' => AssistantActionType::AddPokemon,
            'payload' => ['pokemon_id' => 25, 'display_name' => 'Pikachu'],
            'status' => AssistantActionStatus::Pending,
            'expires_at' => now()->addMinutes(15),
        ];
    }
}
