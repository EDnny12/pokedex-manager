<?php

namespace App\Services;

use App\Contracts\PokemonCatalog;
use App\Exceptions\PokeApiUnavailableException;
use App\Models\PokemonCollectionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class PokemonCollectionService
{
    public function __construct(private PokemonCatalog $pokemonCatalog) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, api_error: string|null}
     */
    public function forUser(User $user): array
    {
        $collectionItems = $user->pokemonCollectionItems()
            ->select(['id', 'user_id', 'pokemon_id', 'nickname', 'notes', 'is_favorite', 'created_at', 'updated_at'])
            ->latest()
            ->get();

        return $this->hydrate($collectionItems);
    }

    /**
     * @param  Collection<int, PokemonCollectionItem>  $collectionItems
     * @return array{items: array<int, array<string, mixed>>, api_error: string|null}
     */
    public function hydrate(Collection $collectionItems): array
    {
        try {
            $pokemonById = collect($this->pokemonCatalog->findMany($collectionItems->pluck('pokemon_id')))
                ->keyBy('id');
            $hasIncompleteCatalogData = $collectionItems->contains(
                fn (PokemonCollectionItem $collectionItem): bool => ! $pokemonById->has($collectionItem->pokemon_id),
            );

            return [
                'items' => $collectionItems
                    ->map(function (PokemonCollectionItem $collectionItem) use ($pokemonById): array {
                        $pokemon = $pokemonById->get($collectionItem->pokemon_id)
                            ?? $this->fallbackPokemon($collectionItem->pokemon_id);

                        return $this->merge($collectionItem, $pokemon);
                    })
                    ->values()
                    ->all(),
                'api_error' => $hasIncompleteCatalogData
                    ? 'No pudimos actualizar todas las fichas de la Pokédex. Tus datos personales siguen seguros.'
                    : null,
            ];
        } catch (PokeApiUnavailableException) {
            return [
                'items' => $collectionItems
                    ->map(fn (PokemonCollectionItem $collectionItem): array => $this->merge(
                        $collectionItem,
                        $this->fallbackPokemon($collectionItem->pokemon_id),
                    ))
                    ->values()
                    ->all(),
                'api_error' => 'No pudimos actualizar las fichas de la Pokédex. Tus datos personales siguen seguros.',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $pokemon
     * @return array<string, mixed>
     */
    public function merge(PokemonCollectionItem $collectionItem, array $pokemon): array
    {
        return [
            ...$pokemon,
            'collection_id' => $collectionItem->id,
            'nickname' => $collectionItem->nickname,
            'notes' => $collectionItem->notes,
            'is_favorite' => $collectionItem->is_favorite,
            'added_at' => $collectionItem->created_at?->toISOString(),
            'updated_at' => $collectionItem->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fallbackPokemon(int $pokemonId): array
    {
        return [
            'id' => $pokemonId,
            'name' => "pokemon-{$pokemonId}",
            'display_name' => "Pokémon #{$pokemonId}",
            'image' => null,
            'types' => [],
            'height_m' => null,
            'weight_kg' => null,
            'abilities' => [],
            'stats' => [],
        ];
    }
}
