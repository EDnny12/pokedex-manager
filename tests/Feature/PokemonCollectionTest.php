<?php

namespace Tests\Feature;

use App\Contracts\PokemonCatalog;
use App\Models\PokemonCollectionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class PokemonCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            'https://pokeapi.co/api/v2/pokemon/25' => Http::response($this->pokemonPayload()),
        ]);
    }

    public function test_authenticated_user_can_add_a_valid_pokemon_to_collection(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('collection.store'), [
            'pokemon_id' => 25,
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $collectionItem = $user->pokemonCollectionItems()->firstOrFail();
        $this->assertModelExists($collectionItem);
        $this->assertSame($user->id, $collectionItem->user_id);
        $this->assertSame(25, $collectionItem->pokemon_id);
    }

    public function test_same_pokemon_is_not_duplicated_for_the_same_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('collection.store'), ['pokemon_id' => 25]);
        $this->actingAs($user)->post(route('collection.store'), ['pokemon_id' => 25]);

        $this->assertSame(1, $user->pokemonCollectionItems()->where('pokemon_id', 25)->count());
    }

    public function test_user_can_update_personal_pokemon_data(): void
    {
        $user = User::factory()->create();
        $collectionItem = PokemonCollectionItem::factory()->for($user)->create(['pokemon_id' => 25]);

        $response = $this->actingAs($user)->patch(route('collection.update', $collectionItem), [
            'nickname' => 'Chispitas',
            'notes' => 'Mi compañero de aventuras.',
            'is_favorite' => true,
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Chispitas', $collectionItem->refresh()->nickname);
        $this->assertTrue($collectionItem->is_favorite);
    }

    public function test_user_cannot_update_another_users_pokemon(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $collectionItem = PokemonCollectionItem::factory()->for($owner)->create([
            'pokemon_id' => 25,
            'nickname' => null,
        ]);

        $response = $this->actingAs($intruder)->patch(route('collection.update', $collectionItem), [
            'nickname' => 'Robado',
            'notes' => null,
            'is_favorite' => false,
        ]);

        $response->assertForbidden();
        $this->assertNull($collectionItem->refresh()->nickname);
    }

    public function test_user_can_delete_owned_pokemon(): void
    {
        $user = User::factory()->create();
        $collectionItem = PokemonCollectionItem::factory()->for($user)->create(['pokemon_id' => 25]);

        $response = $this->actingAs($user)->delete(route('collection.destroy', $collectionItem));

        $response->assertRedirect(route('dashboard'));
        $this->assertModelMissing($collectionItem);
    }

    public function test_user_cannot_delete_another_users_pokemon(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $collectionItem = PokemonCollectionItem::factory()->for($owner)->create(['pokemon_id' => 25]);

        $this->actingAs($intruder)
            ->delete(route('collection.destroy', $collectionItem))
            ->assertForbidden();

        $this->assertModelExists($collectionItem);
    }

    public function test_collection_page_hydrates_personal_data_with_pokeapi_data(): void
    {
        $user = User::factory()->create();
        PokemonCollectionItem::factory()->for($user)->create([
            'pokemon_id' => 25,
            'nickname' => 'Chispitas',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Collection/Index')
            ->has('items', 1)
            ->where('items.0.display_name', 'Pikachu')
            ->where('items.0.nickname', 'Chispitas'));
    }

    public function test_collection_page_reports_partial_catalog_data_without_losing_personal_data(): void
    {
        $user = User::factory()->create();
        PokemonCollectionItem::factory()->for($user)->create([
            'pokemon_id' => 25,
            'nickname' => 'Chispitas',
        ]);

        $this->mock(PokemonCatalog::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findMany')->once()->andReturn([]);
        });

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Collection/Index')
                ->where('items.0.display_name', 'Pokémon #25')
                ->where('items.0.nickname', 'Chispitas')
                ->where(
                    'api_error',
                    'No pudimos actualizar todas las fichas de la Pokédex. Tus datos personales siguen seguros.',
                ));
    }

    public function test_collection_detail_previews_the_impact_before_removing(): void
    {
        $user = User::factory()->create();
        $collectionItem = PokemonCollectionItem::factory()->for($user)->create([
            'pokemon_id' => 25,
        ]);

        $response = $this->actingAs($user)->get(route('collection.show', $collectionItem));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Collection/Show')
            ->where('removalImpact.mode', 'remove')
            ->where('removalImpact.status', 'empties_collection')
            ->where('removalImpact.total', ['before' => 1, 'after' => 0])
            ->where('removalImpact.lost_types', ['electric'])
            ->where('removalImpact.is_partial', false));
    }

    /**
     * @return array<string, mixed>
     */
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
            'stats' => [
                ['base_stat' => 35, 'stat' => ['name' => 'hp']],
                ['base_stat' => 55, 'stat' => ['name' => 'attack']],
                ['base_stat' => 40, 'stat' => ['name' => 'defense']],
                ['base_stat' => 50, 'stat' => ['name' => 'special-attack']],
                ['base_stat' => 50, 'stat' => ['name' => 'special-defense']],
                ['base_stat' => 90, 'stat' => ['name' => 'speed']],
            ],
        ];
    }
}
