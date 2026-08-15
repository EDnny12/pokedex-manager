<?php

namespace Tests\Unit;

use App\Services\PokeApi\PokeApiCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PokeApiCatalogTest extends TestCase
{
    public function test_find_many_reads_all_cached_pokemon_in_one_operation(): void
    {
        $pikachu = $this->normalizedPokemon(25, 'pikachu');
        $mew = $this->normalizedPokemon(151, 'mew');

        Cache::shouldReceive('many')
            ->once()
            ->with(['pokeapi:pokemon:25', 'pokeapi:pokemon:151'])
            ->andReturn([
                'pokeapi:pokemon:25' => $pikachu,
                'pokeapi:pokemon:151' => $mew,
            ]);
        Cache::shouldNotReceive('putMany');
        Http::preventStrayRequests();

        $pokemon = app(PokeApiCatalog::class)->findMany([25, 151, 25]);

        $this->assertSame([$pikachu, $mew], $pokemon);
    }

    public function test_find_many_writes_identifier_aliases_in_one_operation(): void
    {
        config(['services.pokeapi.base_url' => 'https://pokeapi.test/api/v2']);
        Cache::shouldReceive('many')
            ->once()
            ->with(['pokeapi:pokemon:pikachu'])
            ->andReturn(['pokeapi:pokemon:pikachu' => null]);
        Cache::shouldReceive('putMany')
            ->once()
            ->withArgs(function (array $values, int $ttl): bool {
                return $ttl === 86400
                    && array_keys($values) === [
                        'pokeapi:pokemon:pikachu',
                        'pokeapi:pokemon:25',
                    ];
            });
        Http::fake([
            'https://pokeapi.test/api/v2/pokemon/pikachu' => Http::response($this->pokemonPayload()),
        ]);

        $pokemon = app(PokeApiCatalog::class)->findMany(['pikachu']);

        $this->assertSame(25, $pokemon[0]['id']);
    }

    /** @return array<string, mixed> */
    private function normalizedPokemon(int $id, string $name): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'display_name' => ucfirst($name),
            'image' => null,
            'types' => [],
            'height_m' => 0.0,
            'weight_kg' => 0.0,
            'abilities' => [],
            'stats' => [],
            'is_default' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function pokemonPayload(): array
    {
        return [
            'id' => 25,
            'name' => 'pikachu',
            'height' => 4,
            'weight' => 60,
            'sprites' => [],
            'types' => [['slot' => 1, 'type' => ['name' => 'electric']]],
            'abilities' => [],
            'stats' => [],
        ];
    }
}
