<?php

namespace Database\Factories;

use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantMessageAttachment>
 */
class AssistantMessageAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_message_id' => AssistantMessage::factory(),
            'disk' => 'local',
            'path' => 'assistant/test/'.fake()->uuid().'.png',
            'original_name' => 'pokemon.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'width' => 100,
            'height' => 100,
        ];
    }
}
