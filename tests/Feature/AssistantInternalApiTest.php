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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantInternalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.assistant.context_secret' => 'test-assistant-context-secret-at-least-32']);
        Cache::flush();
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

    public function test_catalog_search_combines_ability_and_generation_filters(): void
    {
        Http::fake([
            'https://pokeapi.co/api/v2/pokemon?*' => Http::response([
                'results' => [
                    ['name' => 'pikachu', 'url' => 'https://pokeapi.co/api/v2/pokemon/25/'],
                    ['name' => 'raichu', 'url' => 'https://pokeapi.co/api/v2/pokemon/26/'],
                ],
            ]),
            'https://pokeapi.co/api/v2/ability/static' => Http::response([
                'pokemon' => [
                    ['pokemon' => ['name' => 'pikachu']],
                ],
            ]),
            'https://pokeapi.co/api/v2/generation/generation-i' => Http::response([
                'pokemon_species' => [
                    ['name' => 'pikachu'],
                    ['name' => 'raichu'],
                ],
            ]),
            'https://pokeapi.co/api/v2/pokemon/25' => Http::response($this->pokemonPayload()),
        ]);
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->withToken($this->token($user, $conversation))
            ->getJson('/api/internal/assistant/catalog?ability=static&generation=i')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.id', 25);
    }

    public function test_type_matchup_tool_combines_defensive_multipliers(): void
    {
        $pokemon = [
            ...$this->pokemonPayload(),
            'id' => 598,
            'name' => 'ferrothorn',
            'types' => [
                ['slot' => 1, 'type' => ['name' => 'grass']],
                ['slot' => 2, 'type' => ['name' => 'steel']],
            ],
        ];
        Http::fake([
            'https://pokeapi.co/api/v2/pokemon/598' => Http::response($pokemon),
            'https://pokeapi.co/api/v2/type/grass' => Http::response([
                'damage_relations' => [
                    'no_damage_from' => [],
                    'half_damage_from' => [
                        ['name' => 'water'],
                        ['name' => 'electric'],
                        ['name' => 'grass'],
                        ['name' => 'ground'],
                    ],
                    'double_damage_from' => [
                        ['name' => 'fire'],
                        ['name' => 'ice'],
                        ['name' => 'poison'],
                        ['name' => 'flying'],
                        ['name' => 'bug'],
                    ],
                ],
            ]),
            'https://pokeapi.co/api/v2/type/steel' => Http::response([
                'damage_relations' => [
                    'no_damage_from' => [['name' => 'poison']],
                    'half_damage_from' => [
                        ['name' => 'normal'],
                        ['name' => 'grass'],
                        ['name' => 'ice'],
                        ['name' => 'flying'],
                        ['name' => 'psychic'],
                        ['name' => 'bug'],
                        ['name' => 'rock'],
                        ['name' => 'dragon'],
                        ['name' => 'steel'],
                        ['name' => 'fairy'],
                    ],
                    'double_damage_from' => [
                        ['name' => 'fire'],
                        ['name' => 'fighting'],
                        ['name' => 'ground'],
                    ],
                ],
            ]),
        ]);
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->withToken($this->token($user, $conversation))
            ->getJson('/api/internal/assistant/pokemon/type-matchups?pokemon=598')
            ->assertOk()
            ->assertJsonPath('pokemon.display_name', 'Ferrothorn')
            ->assertJsonFragment(['type' => 'fire', 'multiplier' => 4])
            ->assertJsonFragment(['type' => 'grass', 'multiplier' => 0.25])
            ->assertJsonFragment(['type' => 'poison', 'multiplier' => 0]);
    }

    public function test_evolution_forms_and_moves_are_available_as_bounded_catalog_data(): void
    {
        $pokemon = [
            ...$this->pokemonPayload(),
            'id' => 26,
            'name' => 'raichu',
            'is_default' => true,
            'species' => [
                'name' => 'raichu',
                'url' => 'https://pokeapi.co/api/v2/pokemon-species/26/',
            ],
            'moves' => [[
                'move' => ['name' => 'thunder-shock'],
                'version_group_details' => [[
                    'move_learn_method' => ['name' => 'level-up'],
                    'version_group' => ['name' => 'scarlet-violet'],
                    'level_learned_at' => 1,
                ]],
            ]],
        ];
        Http::fake([
            'https://pokeapi.co/api/v2/pokemon/26' => Http::response($pokemon),
            'https://pokeapi.co/api/v2/pokemon/raichu' => Http::response($pokemon),
            'https://pokeapi.co/api/v2/pokemon-species/26' => Http::response([
                'id' => 26,
                'name' => 'raichu',
                'varieties' => [[
                    'is_default' => true,
                    'pokemon' => ['name' => 'raichu'],
                ]],
                'evolution_chain' => ['url' => 'https://pokeapi.co/api/v2/evolution-chain/10/'],
            ]),
            'https://pokeapi.co/api/v2/evolution-chain/10' => Http::response([
                'id' => 10,
                'chain' => [
                    'is_baby' => true,
                    'species' => [
                        'name' => 'pichu',
                        'url' => 'https://pokeapi.co/api/v2/pokemon-species/172/',
                    ],
                    'evolution_details' => [],
                    'evolves_to' => [[
                        'is_baby' => false,
                        'species' => [
                            'name' => 'raichu',
                            'url' => 'https://pokeapi.co/api/v2/pokemon-species/26/',
                        ],
                        'evolution_details' => [[
                            'trigger' => ['name' => 'use-item'],
                            'item' => ['name' => 'thunder-stone'],
                        ]],
                        'evolves_to' => [],
                    ]],
                ],
            ]),
        ]);
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $token = $this->token($user, $conversation);

        $this->withToken($token)
            ->getJson('/api/internal/assistant/pokemon/forms?pokemon=26')
            ->assertOk()
            ->assertJsonPath('forms.0.is_selected', true);

        $this->withToken($token)
            ->getJson('/api/internal/assistant/pokemon/evolution-chain?pokemon=26')
            ->assertOk()
            ->assertJsonPath('chain.evolves_to.0.species.name', 'raichu')
            ->assertJsonPath('chain.evolves_to.0.evolution_conditions.0.item', 'thunder-stone');

        $this->withToken($token)
            ->getJson('/api/internal/assistant/pokemon/moves?pokemon=26&learn_method=level-up&version_group=scarlet-violet&limit=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.name', 'thunder-shock')
            ->assertJsonPath('truncated', false);
    }

    public function test_move_tool_returns_battle_details(): void
    {
        Http::fake([
            'https://pokeapi.co/api/v2/move/thunderbolt' => Http::response([
                'id' => 85,
                'name' => 'thunderbolt',
                'type' => ['name' => 'electric'],
                'damage_class' => ['name' => 'special'],
                'target' => ['name' => 'selected-pokemon'],
                'power' => 90,
                'accuracy' => 100,
                'pp' => 15,
                'priority' => 0,
                'effect_chance' => 10,
                'effect_entries' => [[
                    'language' => ['name' => 'en'],
                    'short_effect' => 'Has a $effect_chance% chance to paralyze the target.',
                ]],
            ]),
        ]);
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->withToken($this->token($user, $conversation))
            ->getJson('/api/internal/assistant/move?pokemon=thunderbolt')
            ->assertOk()
            ->assertJsonPath('power', 90)
            ->assertJsonPath('effect', 'Has a 10% chance to paralyze the target.');
    }

    public function test_update_request_requires_confirmation_and_changes_only_requested_fields(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        $collectionItem = PokemonCollectionItem::factory()->for($user)->create([
            'pokemon_id' => 25,
            'nickname' => null,
            'notes' => 'Conservar esta nota.',
            'is_favorite' => false,
        ]);
        $token = $this->token($user, $conversation);

        $response = $this->withToken($token)
            ->postJson('/api/internal/assistant/actions', [
                'type' => 'update_pokemon',
                'pokemon' => 25,
                'changes' => [
                    'nickname' => 'Chispitas',
                    'is_favorite' => true,
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payload.changes.nickname', 'Chispitas');

        $this->assertNull($collectionItem->fresh()->nickname);
        $action = AssistantAction::query()->findOrFail($response->json('data.id'));
        $action->update(['status' => AssistantActionStatus::Confirmed]);

        $this->withToken($token)
            ->postJson("/api/internal/assistant/actions/{$action->getKey()}/execute")
            ->assertOk()
            ->assertJsonPath('operation', 'updated');

        $this->withToken($token)
            ->postJson("/api/internal/assistant/actions/{$action->getKey()}/execute")
            ->assertOk()
            ->assertJsonPath('already_executed', true);

        $collectionItem->refresh();
        $this->assertSame('Chispitas', $collectionItem->nickname);
        $this->assertTrue($collectionItem->is_favorite);
        $this->assertSame('Conservar esta nota.', $collectionItem->notes);
    }

    public function test_update_request_rejects_an_empty_change_set(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->withToken($this->token($user, $conversation))
            ->postJson('/api/internal/assistant/actions', [
                'type' => 'update_pokemon',
                'pokemon' => 25,
                'changes' => [],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('changes');
    }

    public function test_update_request_rejects_unknown_collection_fields(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->withToken($this->token($user, $conversation))
            ->postJson('/api/internal/assistant/actions', [
                'type' => 'update_pokemon',
                'pokemon' => 25,
                'changes' => [
                    'nickname' => 'Chispitas',
                    'user_id' => $user->getKey(),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('changes');
    }

    public function test_update_request_rejects_a_pokemon_outside_the_collection(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();

        $this->withToken($this->token($user, $conversation))
            ->postJson('/api/internal/assistant/actions', [
                'type' => 'update_pokemon',
                'pokemon' => 151,
                'changes' => ['is_favorite' => true],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ese Pokémon no forma parte de tu colección.');
    }

    public function test_update_request_does_not_create_a_confirmation_for_unchanged_data(): void
    {
        $user = User::factory()->create();
        $conversation = AssistantConversation::factory()->for($user)->create();
        PokemonCollectionItem::factory()->for($user)->create([
            'pokemon_id' => 25,
            'nickname' => 'Chispitas',
            'notes' => null,
            'is_favorite' => true,
        ]);

        $this->withToken($this->token($user, $conversation))
            ->postJson('/api/internal/assistant/actions', [
                'type' => 'update_pokemon',
                'pokemon' => 25,
                'changes' => [
                    'nickname' => 'Chispitas',
                    'is_favorite' => true,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esos datos ya están guardados en tu colección.');

        $this->assertSame(0, $conversation->actions()->count());
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
