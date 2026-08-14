<?php

namespace App\Services\Assistant;

use App\Contracts\PokemonCatalog;
use App\Exceptions\PokemonNotFoundException;
use App\Models\PokemonCollectionItem;
use App\Models\User;
use App\Services\CollectionInsightService;
use App\Services\PokemonCollectionService;
use Illuminate\Support\Str;

class AssistantToolService
{
    public function __construct(
        private PokemonCatalog $pokemonCatalog,
        private PokemonCollectionService $collectionService,
        private CollectionInsightService $insightService,
    ) {}

    /** @return array<string, mixed> */
    public function collection(User $user, array $filters): array
    {
        $hydrated = $this->collectionService->forUser($user);
        $items = collect($hydrated['items']);
        $search = Str::lower(trim((string) ($filters['search'] ?? '')));
        $type = Str::lower(trim((string) ($filters['type'] ?? '')));

        if ($search !== '') {
            $items = $items->filter(fn (array $pokemon): bool => Str::contains(
                Str::lower(implode(' ', [
                    $pokemon['display_name'],
                    $pokemon['nickname'] ?? '',
                    (string) $pokemon['id'],
                ])),
                $search,
            ));
        }

        if ($type !== '') {
            $items = $items->filter(fn (array $pokemon): bool => in_array($type, $pokemon['types'], true));
        }

        if (array_key_exists('favorite', $filters) && $filters['favorite'] !== null) {
            $items = $items->where('is_favorite', (bool) $filters['favorite']);
        }

        return [
            'items' => $items->take((int) ($filters['limit'] ?? 20))->values()->all(),
            'total' => $items->count(),
            'catalog_available' => $hydrated['api_error'] === null,
        ];
    }

    /** @return array<string, mixed> */
    public function ownedPokemon(User $user, int|string $identifier): array
    {
        $item = $this->resolveOwnedItem($user, $identifier);

        if (! $item instanceof PokemonCollectionItem) {
            throw new PokemonNotFoundException((string) $identifier);
        }

        return $this->collectionService->merge($item, $this->pokemonCatalog->find($item->pokemon_id));
    }

    /** @return array<string, mixed> */
    public function summary(User $user): array
    {
        $collection = $this->collectionService->forUser($user);

        return $this->insightService->calculate($collection['items'], $this->pokemonCatalog->types());
    }

    /** @return array<string, mixed> */
    public function catalog(User $user, string $query, string $type, int $limit): array
    {
        $result = $this->pokemonCatalog->browse($query, $type, 1, $limit);
        $ownedPokemonIds = $user->pokemonCollectionItems()->pluck('pokemon_id')->all();

        return [
            'items' => collect($result['data'])
                ->map(fn (array $pokemon): array => [
                    ...$pokemon,
                    'in_collection' => in_array($pokemon['id'], $ownedPokemonIds, true),
                ])
                ->all(),
            'total' => $result['meta']['total'],
        ];
    }

    /** @return array<string, mixed> */
    public function pokemon(int|string $identifier, ?User $user = null): array
    {
        $pokemon = $this->pokemonCatalog->find($identifier);

        return [
            ...$pokemon,
            'in_collection' => $user?->pokemonCollectionItems()
                ->where('pokemon_id', $pokemon['id'])
                ->exists() ?? false,
        ];
    }

    /** @param  array<int, int|string>  $identifiers */
    public function compare(array $identifiers): array
    {
        return ['items' => $this->pokemonCatalog->findMany($identifiers)];
    }

    public function resolvePokemonId(int|string $identifier): int
    {
        return (int) $this->pokemonCatalog->find($identifier)['id'];
    }

    private function resolveOwnedItem(User $user, int|string $identifier): ?PokemonCollectionItem
    {
        if (is_numeric($identifier)) {
            return $user->pokemonCollectionItems()
                ->where('pokemon_id', (int) $identifier)
                ->first();
        }

        $normalizedIdentifier = Str::lower(trim((string) $identifier));
        $nicknameMatch = $user->pokemonCollectionItems()
            ->whereRaw('LOWER(nickname) = ?', [$normalizedIdentifier])
            ->first();

        if ($nicknameMatch instanceof PokemonCollectionItem) {
            return $nicknameMatch;
        }

        try {
            $pokemonId = $this->resolvePokemonId($identifier);
        } catch (PokemonNotFoundException) {
            return null;
        }

        return $user->pokemonCollectionItems()->where('pokemon_id', $pokemonId)->first();
    }
}
