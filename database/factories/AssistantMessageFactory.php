<?php

namespace Database\Factories;

use App\Enums\AssistantMessageRole;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantMessage>
 */
class AssistantMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => AssistantConversation::factory(),
            'role' => AssistantMessageRole::User,
            'content' => fake()->sentence(),
            'metadata' => null,
            'client_message_id' => fake()->uuid(),
        ];
    }
}
