<?php

namespace App\Services\PokeApi;

use App\Contracts\PokemonCatalog;
use App\Exceptions\PokeApiUnavailableException;
use App\Exceptions\PokemonNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PokeApiCatalog implements PokemonCatalog
{
    /**
     * @var list<string>
     */
    private const TYPES = [
        'normal',
        'fire',
        'water',
        'electric',
        'grass',
        'ice',
        'fighting',
        'poison',
        'ground',
        'flying',
        'psychic',
        'bug',
        'rock',
        'ghost',
        'dragon',
        'dark',
        'steel',
        'fairy',
    ];

    public function find(int|string $identifier): array
    {
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        $cacheKey = $this->pokemonCacheKey($normalizedIdentifier);

        return Cache::remember($cacheKey, $this->cacheTtl(), function () use ($normalizedIdentifier): array {
            $response = $this->request("pokemon/{$normalizedIdentifier}");

            if ($response->notFound()) {
                throw new PokemonNotFoundException($normalizedIdentifier);
            }

            if ($response->failed()) {
                throw new PokeApiUnavailableException("pokemon/{$normalizedIdentifier}");
            }

            return $this->normalizePokemon($response->json());
        });
    }

    public function findMany(iterable $identifiers): array
    {
        $orderedIdentifiers = collect($identifiers)
            ->map(fn (int|string $identifier): string => $this->normalizeIdentifier($identifier))
            ->unique()
            ->values();

        if ($orderedIdentifiers->isEmpty()) {
            return [];
        }

        $pokemonByIdentifier = [];
        $missingIdentifiers = [];

        foreach ($orderedIdentifiers as $identifier) {
            $cachedPokemon = Cache::get($this->pokemonCacheKey($identifier));

            if (is_array($cachedPokemon)) {
                $pokemonByIdentifier[$identifier] = $cachedPokemon;
            } else {
                $missingIdentifiers[] = $identifier;
            }
        }

        if ($missingIdentifiers !== []) {
            $responses = Http::pool(fn (Pool $pool): array => collect($missingIdentifiers)
                ->mapWithKeys(fn (string $identifier): array => [
                    $identifier => $pool
                        ->as($identifier)
                        ->acceptJson()
                        ->connectTimeout($this->connectTimeout())
                        ->timeout($this->timeout())
                        ->get($this->endpoint("pokemon/{$identifier}")),
                ])
                ->all());

            foreach ($missingIdentifiers as $identifier) {
                $response = $responses[$identifier] ?? null;

                if (! $response instanceof Response || ! $response->successful()) {
                    continue;
                }

                $pokemon = $this->normalizePokemon($response->json());
                Cache::put($this->pokemonCacheKey($identifier), $pokemon, $this->cacheTtl());
                Cache::put($this->pokemonCacheKey((string) $pokemon['id']), $pokemon, $this->cacheTtl());
                $pokemonByIdentifier[$identifier] = $pokemon;
            }

            if ($pokemonByIdentifier === []) {
                throw new PokeApiUnavailableException('pokemon');
            }
        }

        return $orderedIdentifiers
            ->map(fn (string $identifier): ?array => $pokemonByIdentifier[$identifier] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function browse(string $query, string $type, int $page, int $perPage): array
    {
        $pokemonIndex = collect($this->pokemonIndex());
        $normalizedQuery = Str::of($query)->trim()->lower()->replace(' ', '-')->toString();

        if ($normalizedQuery !== '') {
            $pokemonIndex = $pokemonIndex->filter(function (array $pokemon) use ($normalizedQuery): bool {
                if (is_numeric($normalizedQuery)) {
                    return $pokemon['id'] === (int) $normalizedQuery;
                }

                return Str::contains($pokemon['name'], $normalizedQuery);
            });
        }

        if ($type !== '') {
            $pokemonOfType = $this->pokemonNamesForType($type);
            $pokemonIndex = $pokemonIndex->filter(
                fn (array $pokemon): bool => isset($pokemonOfType[$pokemon['name']]),
            );
        }

        $pokemonIndex = $pokemonIndex->values();
        $total = $pokemonIndex->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $lastPage);
        $pageEntries = $pokemonIndex->slice(($currentPage - 1) * $perPage, $perPage)->values();

        return [
            'data' => $this->findMany($pageEntries->pluck('id')),
            'meta' => [
                'current_page' => $currentPage,
                'from' => $total === 0 ? 0 : (($currentPage - 1) * $perPage) + 1,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => min($currentPage * $perPage, $total),
                'total' => $total,
            ],
        ];
    }

    public function types(): array
    {
        return self::TYPES;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function pokemonIndex(): array
    {
        return Cache::remember('pokeapi:pokemon-index', $this->cacheTtl(), function (): array {
            $response = $this->request('pokemon', ['limit' => 100000, 'offset' => 0]);

            if ($response->failed()) {
                throw new PokeApiUnavailableException('pokemon');
            }

            return collect($response->json('results', []))
                ->map(function (array $pokemon): ?array {
                    $id = (int) Str::afterLast(Str::beforeLast($pokemon['url'], '/'), '/');

                    return $id > 0 ? ['id' => $id, 'name' => $pokemon['name']] : null;
                })
                ->filter()
                ->values()
                ->all();
        });
    }

    /**
     * @return array<string, true>
     */
    private function pokemonNamesForType(string $type): array
    {
        return Cache::remember("pokeapi:type:{$type}", $this->cacheTtl(), function () use ($type): array {
            $response = $this->request("type/{$type}");

            if ($response->failed()) {
                throw new PokeApiUnavailableException("type/{$type}");
            }

            return collect($response->json('pokemon', []))
                ->mapWithKeys(fn (array $entry): array => [$entry['pokemon']['name'] => true])
                ->all();
        });
    }

    /**
     * @param  array<string, mixed>  $pokemon
     * @return array<string, mixed>
     */
    private function normalizePokemon(array $pokemon): array
    {
        $name = (string) Arr::get($pokemon, 'name', 'pokemon');

        return [
            'id' => (int) Arr::get($pokemon, 'id'),
            'name' => $name,
            'display_name' => Str::of($name)->replace('-', ' ')->title()->toString(),
            'image' => Arr::get($pokemon, 'sprites.other.official-artwork.front_default')
                ?? Arr::get($pokemon, 'sprites.other.home.front_default')
                ?? Arr::get($pokemon, 'sprites.front_default'),
            'types' => collect(Arr::get($pokemon, 'types', []))
                ->sortBy('slot')
                ->pluck('type.name')
                ->values()
                ->all(),
            'height_m' => round(((int) Arr::get($pokemon, 'height')) / 10, 1),
            'weight_kg' => round(((int) Arr::get($pokemon, 'weight')) / 10, 1),
            'abilities' => collect(Arr::get($pokemon, 'abilities', []))
                ->sortBy('slot')
                ->map(fn (array $ability): array => [
                    'name' => Str::of($ability['ability']['name'])->replace('-', ' ')->title()->toString(),
                    'is_hidden' => (bool) $ability['is_hidden'],
                ])
                ->values()
                ->all(),
            'stats' => collect(Arr::get($pokemon, 'stats', []))
                ->mapWithKeys(fn (array $stat): array => [
                    $stat['stat']['name'] => (int) $stat['base_stat'],
                ])
                ->all(),
        ];
    }

    /**
     * @param  array<string, int|string>  $query
     */
    private function request(string $endpoint, array $query = []): Response
    {
        try {
            return $this->client()->get($endpoint, $query);
        } catch (ConnectionException $exception) {
            throw new PokeApiUnavailableException($endpoint, previous: $exception);
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.pokeapi.base_url'))
            ->acceptJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->retry(
                [150, 400],
                fn (Throwable $exception): bool => $exception instanceof ConnectionException,
                throw: false,
            );
    }

    private function endpoint(string $endpoint): string
    {
        return Str::finish((string) config('services.pokeapi.base_url'), '/').ltrim($endpoint, '/');
    }

    private function normalizeIdentifier(int|string $identifier): string
    {
        return Str::of((string) $identifier)->trim()->lower()->replace(' ', '-')->toString();
    }

    private function pokemonCacheKey(string $identifier): string
    {
        return "pokeapi:pokemon:{$identifier}";
    }

    private function timeout(): int
    {
        return (int) config('services.pokeapi.timeout', 8);
    }

    private function connectTimeout(): int
    {
        return (int) config('services.pokeapi.connect_timeout', 3);
    }

    private function cacheTtl(): int
    {
        return (int) config('services.pokeapi.cache_ttl', 86400);
    }
}
