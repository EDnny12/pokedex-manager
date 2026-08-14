<?php

namespace Tests\Feature;

use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\PokemonCollectionItem;
use App\Models\User;
use App\Services\Assistant\AssistantContextSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantInternalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.assistant.context_secret' => 'test-assistant-context-secret-at-least-32']);
        Http::preventStrayRequests();
        Http::fake([
            'https://pokeapi.co/api/v2/pokemon/25' => Http::response($this->pokemonPayload()),
        ]);
    }

    public function test_invalid_context_cannot_access_internal_tools(): void
    {
        $this->withToken('invalid-token')
            ->getJson('/api/internal/assistant/collection')
            ->assertUnauthorized();
    }

    public function test_signed_context_is_scoped_to_its_collection(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        PokemonCollectionItem::factory()->for($user)->create(['pokemon_id' => 25]);
        PokemonCollectionItem::factory()->for($otherUser)->create(['pokemon_id' => 7]);

        $this->withToken($this->token($user, $conversation))
            ->getJson('/api/internal/assistant/collection')
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', 25);
    }

    public function test_action_request_does_not_mutate_collection_before_confirmation(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $response = $this->withToken($this->token($user, $conversation))
            ->postJson('/api/internal/assistant/actions', [
                'type' => 'add_pokemon',
                'pokemon' => 25,
            ]);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->assertSame(0, $user->pokemonCollectionItems()->count());
    }

    public function test_confirmed_action_can_execute_once(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $action = AssistantAction::factory()
            ->for($user)
            ->for($conversation, 'conversation')
            ->create([
                'type' => AssistantActionType::AddPokemon,
                'status' => AssistantActionStatus::Confirmed,
            ]);
        $token = $this->token($user, $conversation);

        $this->withToken($token)
            ->postJson("/api/internal/assistant/actions/{$action->getKey()}/execute")
            ->assertOk()
            ->assertJsonPath('status', 'executed');

        $this->withToken($token)
            ->postJson("/api/internal/assistant/actions/{$action->getKey()}/execute")
            ->assertOk()
            ->assertJsonPath('already_executed', true);

        $this->assertSame(1, $user->pokemonCollectionItems()->where('pokemon_id', 25)->count());
    }

    private function token(User $user, AssistantConversation $conversation): string
    {
        return app(AssistantContextSigner::class)->for($user, $conversation);
    }

    /** @return array<string, mixed> */
    private function pokemonPayload(): array
    {
        return [
            'id' => 25,
            'name' => 'pikachu',
            'height' => 4,
            'weight' => 60,
            'sprites' => [
                'front_default' => 'https://example.test/pikachu.png',
                'other' => ['official-artwork' => ['front_default' => 'https://example.test/pikachu-art.png']],
            ],
            'types' => [['slot' => 1, 'type' => ['name' => 'electric']]],
            'abilities' => [['slot' => 1, 'is_hidden' => false, 'ability' => ['name' => 'static']]],
            'stats' => [['base_stat' => 90, 'stat' => ['name' => 'speed']]],
        ];
    }
}
