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

    public function search(
        string $query,
        string $type,
        string $ability,
        string $generation,
        int $limit,
    ): array {
        $pokemonIndex = collect($this->pokemonIndex());
        $normalizedQuery = $this->normalizeIdentifier($query);

        if ($normalizedQuery !== '') {
            $pokemonIndex = $pokemonIndex->filter(function (array $pokemon) use ($normalizedQuery): bool {
                if (is_numeric($normalizedQuery)) {
                    return $pokemon['id'] === (int) $normalizedQuery;
                }

                return Str::contains($pokemon['name'], $normalizedQuery);
            });
        }

        $attributeFilters = [];

        if ($type !== '') {
            $attributeFilters[] = $this->pokemonNamesForType($this->normalizeIdentifier($type));
        }

        if ($ability !== '') {
            $attributeFilters[] = $this->pokemonNamesForAbility($this->normalizeIdentifier($ability));
        }

        if ($generation !== '') {
            $attributeFilters[] = $this->pokemonNamesForGeneration($this->normalizeGeneration($generation));
        }

        foreach ($attributeFilters as $allowedNames) {
            $pokemonIndex = $pokemonIndex->filter(
                fn (array $pokemon): bool => isset($allowedNames[$pokemon['name']]),
            );
        }

        $pokemonIndex = $pokemonIndex->values();
        $total = $pokemonIndex->count();

        return [
            'data' => $this->findMany($pokemonIndex->take($limit)->pluck('id')),
            'meta' => [
                'current_page' => 1,
                'from' => $total === 0 ? 0 : 1,
                'last_page' => max(1, (int) ceil($total / $limit)),
                'per_page' => $limit,
                'to' => min($limit, $total),
                'total' => $total,
            ],
        ];
    }

    public function forms(int|string $identifier): array
    {
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);

        return Cache::remember("pokeapi:forms:{$normalizedIdentifier}", $this->cacheTtl(), function () use ($normalizedIdentifier): array {
            $pokemonResponse = $this->request("pokemon/{$normalizedIdentifier}");

            if ($pokemonResponse->notFound()) {
                throw new PokemonNotFoundException($normalizedIdentifier);
            }

            if ($pokemonResponse->failed()) {
                throw new PokeApiUnavailableException("pokemon/{$normalizedIdentifier}");
            }

            $pokemon = $pokemonResponse->json();
            $speciesEndpoint = $this->apiEndpointFromUrl((string) Arr::get($pokemon, 'species.url'));
            $speciesResponse = $this->request($speciesEndpoint);

            if ($speciesResponse->failed()) {
                throw new PokeApiUnavailableException($speciesEndpoint);
            }

            $formNames = collect($speciesResponse->json('varieties', []))
                ->pluck('pokemon.name')
                ->filter()
                ->values();

            return [
                'selected_form' => $this->normalizePokemon($pokemon),
                'species' => [
                    'id' => (int) $speciesResponse->json('id'),
                    'name' => (string) $speciesResponse->json('name'),
                    'display_name' => $this->displayName((string) $speciesResponse->json('name')),
                ],
                'forms' => collect($this->findMany($formNames))
                    ->map(fn (array $form): array => [
                        ...$form,
                        'is_selected' => $form['id'] === (int) Arr::get($pokemon, 'id'),
                    ])
                    ->values()
                    ->all(),
            ];
        });
    }

    public function evolutionChain(int|string $identifier): array
    {
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);

        return Cache::remember("pokeapi:evolution-chain:{$normalizedIdentifier}", $this->cacheTtl(), function () use ($normalizedIdentifier): array {
            $speciesResponse = $this->request("pokemon-species/{$normalizedIdentifier}");

            if ($speciesResponse->notFound()) {
                $pokemonResponse = $this->request("pokemon/{$normalizedIdentifier}");

                if ($pokemonResponse->notFound()) {
                    throw new PokemonNotFoundException($normalizedIdentifier);
                }

                if ($pokemonResponse->failed()) {
                    throw new PokeApiUnavailableException("pokemon/{$normalizedIdentifier}");
                }

                $speciesResponse = $this->request($this->apiEndpointFromUrl((string) $pokemonResponse->json('species.url')));
            }

            if ($speciesResponse->failed()) {
                throw new PokeApiUnavailableException("pokemon-species/{$normalizedIdentifier}");
            }

            $chainEndpoint = $this->apiEndpointFromUrl((string) $speciesResponse->json('evolution_chain.url'));
            $chainResponse = $this->request($chainEndpoint);

            if ($chainResponse->failed()) {
                throw new PokeApiUnavailableException($chainEndpoint);
            }

            return [
                'species' => [
                    'id' => (int) $speciesResponse->json('id'),
                    'name' => (string) $speciesResponse->json('name'),
                    'display_name' => $this->displayName((string) $speciesResponse->json('name')),
                ],
                'chain_id' => (int) $chainResponse->json('id'),
                'chain' => $this->normalizeEvolutionLink($chainResponse->json('chain', [])),
            ];
        });
    }

    public function typeMatchups(int|string $identifier): array
    {
        $pokemon = $this->find($identifier);
        $multipliers = array_fill_keys(self::TYPES, 1.0);

        foreach ($pokemon['types'] as $type) {
            $relations = $this->damageRelationsForType($type);

            foreach ($relations['no_damage_from'] as $attackingType) {
                $multipliers[$attackingType] = 0.0;
            }

            foreach ($relations['half_damage_from'] as $attackingType) {
                $multipliers[$attackingType] *= 0.5;
            }

            foreach ($relations['double_damage_from'] as $attackingType) {
                $multipliers[$attackingType] *= 2;
            }
        }

        $entries = collect($multipliers)
            ->map(fn (float $multiplier, string $type): array => [
                'type' => $type,
                'multiplier' => $multiplier,
            ]);

        return [
            'pokemon' => [
                'id' => $pokemon['id'],
                'name' => $pokemon['name'],
                'display_name' => $pokemon['display_name'],
                'types' => $pokemon['types'],
            ],
            'defensive_matchups' => [
                'weaknesses' => $entries->filter(fn (array $entry): bool => $entry['multiplier'] > 1)->values()->all(),
                'resistances' => $entries->filter(fn (array $entry): bool => $entry['multiplier'] > 0 && $entry['multiplier'] < 1)->values()->all(),
                'immunities' => $entries->filter(fn (array $entry): bool => $entry['multiplier'] === 0.0)->values()->all(),
            ],
        ];
    }

    public function moves(
        int|string $identifier,
        string $learnMethod,
        string $versionGroup,
        int $limit,
    ): array {
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);
        $normalizedMethod = $this->normalizeIdentifier($learnMethod);
        $normalizedVersion = $this->normalizeIdentifier($versionGroup);
        $cacheKey = "pokeapi:moves:{$normalizedIdentifier}:{$normalizedMethod}:{$normalizedVersion}";

        $result = Cache::remember($cacheKey, $this->cacheTtl(), function () use ($normalizedIdentifier, $normalizedMethod, $normalizedVersion): array {
            $response = $this->request("pokemon/{$normalizedIdentifier}");

            if ($response->notFound()) {
                throw new PokemonNotFoundException($normalizedIdentifier);
            }

            if ($response->failed()) {
                throw new PokeApiUnavailableException("pokemon/{$normalizedIdentifier}");
            }

            $pokemon = $this->normalizePokemon($response->json());
            $moves = collect($response->json('moves', []))
                ->map(function (array $entry) use ($normalizedMethod, $normalizedVersion): ?array {
                    $learningDetails = collect(Arr::get($entry, 'version_group_details', []))
                        ->filter(fn (array $detail): bool => ($normalizedMethod === '' || Arr::get($detail, 'move_learn_method.name') === $normalizedMethod)
                            && ($normalizedVersion === '' || Arr::get($detail, 'version_group.name') === $normalizedVersion))
                        ->map(fn (array $detail): array => [
                            'method' => Arr::get($detail, 'move_learn_method.name'),
                            'version_group' => Arr::get($detail, 'version_group.name'),
                            'level_learned_at' => (int) Arr::get($detail, 'level_learned_at'),
                        ])
                        ->values();

                    if ($learningDetails->isEmpty()) {
                        return null;
                    }

                    $name = (string) Arr::get($entry, 'move.name');

                    return [
                        'name' => $name,
                        'display_name' => $this->displayName($name),
                        'learning_details' => $learningDetails->all(),
                    ];
                })
                ->filter()
                ->sortBy(fn (array $move): string => sprintf(
                    '%05d-%s',
                    collect($move['learning_details'])->min('level_learned_at') ?? 0,
                    $move['name'],
                ))
                ->values();

            return [
                'pokemon' => [
                    'id' => $pokemon['id'],
                    'name' => $pokemon['name'],
                    'display_name' => $pokemon['display_name'],
                ],
                'filters' => [
                    'learn_method' => $normalizedMethod !== '' ? $normalizedMethod : null,
                    'version_group' => $normalizedVersion !== '' ? $normalizedVersion : null,
                ],
                'items' => $moves->all(),
                'total' => $moves->count(),
            ];
        });

        return [
            ...$result,
            'items' => array_slice($result['items'], 0, $limit),
            'limit' => $limit,
            'truncated' => $result['total'] > $limit,
        ];
    }

    public function move(int|string $identifier): array
    {
        $normalizedIdentifier = $this->normalizeIdentifier($identifier);

        return Cache::remember("pokeapi:move:{$normalizedIdentifier}", $this->cacheTtl(), function () use ($normalizedIdentifier): array {
            $response = $this->request("move/{$normalizedIdentifier}");

            if ($response->notFound()) {
                throw new PokemonNotFoundException($normalizedIdentifier);
            }

            if ($response->failed()) {
                throw new PokeApiUnavailableException("move/{$normalizedIdentifier}");
            }

            $move = $response->json();
            $name = (string) Arr::get($move, 'name');
            $effect = collect(Arr::get($move, 'effect_entries', []))
                ->firstWhere('language.name', 'en');

            return [
                'id' => (int) Arr::get($move, 'id'),
                'name' => $name,
                'display_name' => $this->displayName($name),
                'type' => Arr::get($move, 'type.name'),
                'damage_class' => Arr::get($move, 'damage_class.name'),
                'target' => Arr::get($move, 'target.name'),
                'power' => Arr::get($move, 'power'),
                'accuracy' => Arr::get($move, 'accuracy'),
                'pp' => Arr::get($move, 'pp'),
                'priority' => (int) Arr::get($move, 'priority'),
                'effect_chance' => Arr::get($move, 'effect_chance'),
                'effect' => is_array($effect)
                    ? Str::replace('$effect_chance', (string) Arr::get($move, 'effect_chance', ''), (string) Arr::get($effect, 'short_effect'))
                    : null,
            ];
        });
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

            if ($response->notFound()) {
                return [];
            }

            if ($response->failed()) {
                throw new PokeApiUnavailableException("type/{$type}");
            }

            return collect($response->json('pokemon', []))
                ->mapWithKeys(fn (array $entry): array => [$entry['pokemon']['name'] => true])
                ->all();
        });
    }

    /** @return array<string, true> */
    private function pokemonNamesForAbility(string $ability): array
    {
        return Cache::remember("pokeapi:ability-pokemon:{$ability}", $this->cacheTtl(), function () use ($ability): array {
            $response = $this->request("ability/{$ability}");

            if ($response->notFound()) {
                return [];
            }

            if ($response->failed()) {
                throw new PokeApiUnavailableException("ability/{$ability}");
            }

            return collect($response->json('pokemon', []))
                ->mapWithKeys(fn (array $entry): array => [$entry['pokemon']['name'] => true])
                ->all();
        });
    }

    /** @return array<string, true> */
    private function pokemonNamesForGeneration(string $generation): array
    {
        return Cache::remember("pokeapi:generation-pokemon:{$generation}", $this->cacheTtl(), function () use ($generation): array {
            $response = $this->request("generation/{$generation}");

            if ($response->notFound()) {
                return [];
            }

            if ($response->failed()) {
                throw new PokeApiUnavailableException("generation/{$generation}");
            }

            return collect($response->json('pokemon_species', []))
                ->mapWithKeys(fn (array $entry): array => [$entry['name'] => true])
                ->all();
        });
    }

    /** @return array{no_damage_from: list<string>, half_damage_from: list<string>, double_damage_from: list<string>} */
    private function damageRelationsForType(string $type): array
    {
        return Cache::remember("pokeapi:type-relations:{$type}", $this->cacheTtl(), function () use ($type): array {
            $response = $this->request("type/{$type}");

            if ($response->failed()) {
                throw new PokeApiUnavailableException("type/{$type}");
            }

            return [
                'no_damage_from' => $response->collect('damage_relations.no_damage_from')->pluck('name')->all(),
                'half_damage_from' => $response->collect('damage_relations.half_damage_from')->pluck('name')->all(),
                'double_damage_from' => $response->collect('damage_relations.double_damage_from')->pluck('name')->all(),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>
     */
    private function normalizeEvolutionLink(array $link): array
    {
        $speciesName = (string) Arr::get($link, 'species.name');

        return [
            'species' => [
                'id' => $this->resourceId((string) Arr::get($link, 'species.url')),
                'name' => $speciesName,
                'display_name' => $this->displayName($speciesName),
            ],
            'is_baby' => (bool) Arr::get($link, 'is_baby'),
            'evolution_conditions' => collect(Arr::get($link, 'evolution_details', []))
                ->map(fn (array $details): array => array_filter([
                    'trigger' => Arr::get($details, 'trigger.name'),
                    'min_level' => Arr::get($details, 'min_level'),
                    'item' => Arr::get($details, 'item.name'),
                    'held_item' => Arr::get($details, 'held_item.name'),
                    'known_move' => Arr::get($details, 'known_move.name'),
                    'known_move_type' => Arr::get($details, 'known_move_type.name'),
                    'location' => Arr::get($details, 'location.name'),
                    'min_happiness' => Arr::get($details, 'min_happiness'),
                    'min_beauty' => Arr::get($details, 'min_beauty'),
                    'min_affection' => Arr::get($details, 'min_affection'),
                    'time_of_day' => Arr::get($details, 'time_of_day') ?: null,
                    'trade_species' => Arr::get($details, 'trade_species.name'),
                    'needs_overworld_rain' => Arr::get($details, 'needs_overworld_rain') ?: null,
                    'turn_upside_down' => Arr::get($details, 'turn_upside_down') ?: null,
                ], fn (mixed $value): bool => $value !== null))
                ->values()
                ->all(),
            'evolves_to' => collect(Arr::get($link, 'evolves_to', []))
                ->map(fn (array $nextLink): array => $this->normalizeEvolutionLink($nextLink))
                ->values()
                ->all(),
        ];
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
            'display_name' => $this->displayName($name),
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
            'is_default' => (bool) Arr::get($pokemon, 'is_default', true),
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

    private function normalizeGeneration(string $generation): string
    {
        $normalized = $this->normalizeIdentifier($generation);

        if (is_numeric($normalized) || str_starts_with($normalized, 'generation-')) {
            return $normalized;
        }

        return "generation-{$normalized}";
    }

    private function displayName(string $name): string
    {
        return Str::of($name)->replace('-', ' ')->title()->toString();
    }

    private function apiEndpointFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return Str::of($path)->after('/api/v2/')->trim('/')->toString();
    }

    private function resourceId(string $url): int
    {
        return (int) Str::afterLast(Str::beforeLast($url, '/'), '/');
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
