<?php

namespace Tests\Feature;

use App\Contracts\PokemonCatalog;
use App\Exceptions\PokeApiUnavailableException;
use App\Models\PokemonCollectionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class PokedexTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_browse_pokedex(): void
    {
        $this->mockCatalog();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('pokedex.index', ['q' => 'pika']));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pokedex/Index')
            ->where('filters.q', 'pika')
            ->has('catalog.data', 1)
            ->where('catalog.data.0.display_name', 'Pikachu'));
    }

    public function test_guest_is_redirected_from_pokedex(): void
    {
        $this->get(route('pokedex.index'))->assertRedirect(route('login'));
    }

    public function test_pokedex_failure_is_rendered_as_a_recoverable_error_state(): void
    {
        $user = User::factory()->create();

        $this->mock(PokemonCatalog::class, function (MockInterface $mock): void {
            $mock->shouldReceive('browse')->once()->andThrow(new PokeApiUnavailableException('pokemon'));
            $mock->shouldReceive('types')->once()->andReturn(['electric']);
        });

        $response = $this->actingAs($user)->get(route('pokedex.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pokedex/Index')
            ->where('catalog.data', [])
            ->where('apiError', 'No pudimos cargar la Pokédex. Tu colección no se verá afectada.'));
    }

    public function test_authenticated_user_can_compare_two_pokemon(): void
    {
        $user = User::factory()->create();
        $bulbasaur = $this->pokemonData(1, 'bulbasaur', ['hp' => 45, 'speed' => 45]);
        $pikachu = $this->pokemonData(25, 'pikachu', ['hp' => 35, 'speed' => 90]);

        $this->mock(PokemonCatalog::class, function (MockInterface $mock) use ($bulbasaur, $pikachu): void {
            $mock->shouldReceive('find')->once()->with('bulbasaur')->andReturn($bulbasaur);
            $mock->shouldReceive('find')->once()->with('pikachu')->andReturn($pikachu);
        });

        $response = $this->actingAs($user)->get(route('compare.index', [
            'left' => 'bulbasaur',
            'right' => 'pikachu',
        ]));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Compare/Index')
            ->where('leftPokemon.display_name', 'Bulbasaur')
            ->where('rightPokemon.display_name', 'Pikachu')
            ->where('rightPokemon.stats.speed', 90));
    }

    public function test_pokedex_detail_previews_the_collection_impact_before_adding(): void
    {
        $user = User::factory()->create();
        PokemonCollectionItem::factory()->for($user)->create(['pokemon_id' => 1]);
        $bulbasaur = $this->pokemonData(1, 'bulbasaur', ['speed' => 45], ['grass', 'poison']);
        $pikachu = $this->pokemonData(25, 'pikachu', ['speed' => 90], ['electric']);

        $this->mock(PokemonCatalog::class, function (MockInterface $mock) use ($bulbasaur, $pikachu): void {
            $mock->shouldReceive('find')->once()->with(25)->andReturn($pikachu);
            $mock->shouldReceive('findMany')->once()->andReturn([$bulbasaur]);
        });

        $response = $this->actingAs($user)->get(route('pokedex.show', 25));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Pokedex/Show')
            ->where('pokemon.display_name', 'Pikachu')
            ->where('collectionId', null)
            ->where('additionImpact.mode', 'add')
            ->where('additionImpact.status', 'expands')
            ->where('additionImpact.total', ['before' => 1, 'after' => 2])
            ->where('additionImpact.new_types', ['electric'])
            ->where('additionImpact.is_partial', false));
    }

    private function mockCatalog(): void
    {
        $pokemon = $this->pokemonData(25, 'pikachu', ['speed' => 90]);

        $this->mock(PokemonCatalog::class, function (MockInterface $mock) use ($pokemon): void {
            $mock->shouldReceive('browse')->once()->andReturn([
                'data' => [$pokemon],
                'meta' => ['current_page' => 1, 'from' => 1, 'last_page' => 1, 'per_page' => 18, 'to' => 1, 'total' => 1],
            ]);
            $mock->shouldReceive('types')->once()->andReturn(['electric']);
        });
    }

    /**
     * @param  array<string, int>  $stats
     * @param  list<string>  $types
     * @return array<string, mixed>
     */
    private function pokemonData(int $id, string $name, array $stats, array $types = ['electric']): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'display_name' => ucfirst($name),
            'image' => null,
            'types' => $types,
            'height_m' => 0.4,
            'weight_kg' => 6.0,
            'abilities' => [],
            'stats' => $stats,
        ];
    }
}
