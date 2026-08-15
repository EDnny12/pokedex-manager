<?php

namespace Tests\Feature;

use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\PokemonCollectionItem;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_user_must_own_its_conversation(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($owner)->create();

        $this->expectException(QueryException::class);

        DB::transaction(fn () => AssistantAction::factory()
            ->for($otherUser)
            ->for($conversation, 'conversation')
            ->create());
    }

    public function test_pokemon_identifier_must_be_positive(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::transaction(fn () => PokemonCollectionItem::factory()
            ->for($user)
            ->create(['pokemon_id' => 0]));
    }

    public function test_a_user_message_can_have_only_one_assistant_reply(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $userMessage = AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create();

        AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create([
                'role' => 'assistant',
                'client_message_id' => null,
                'reply_to_message_id' => $userMessage->getKey(),
            ]);

        $this->expectException(QueryException::class);

        DB::transaction(fn () => AssistantMessage::factory()
            ->for($conversation, 'conversation')
            ->create([
                'role' => 'assistant',
                'client_message_id' => null,
                'reply_to_message_id' => $userMessage->getKey(),
            ]));
    }
}
