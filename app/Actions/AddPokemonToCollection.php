<?php

namespace App\Actions;

use App\Contracts\PokemonCatalog;
use App\Models\PokemonCollectionItem;
use App\Models\User;

class AddPokemonToCollection
{
    public function __construct(private PokemonCatalog $pokemonCatalog) {}

    public function handle(User $user, int $pokemonId): PokemonCollectionItem
    {
        return $this->persistValidated($user, $this->resolvePokemonId($pokemonId));
    }

    public function resolvePokemonId(int $pokemonId): int
    {
        $pokemon = $this->pokemonCatalog->find($pokemonId);

        return (int) $pokemon['id'];
    }

    public function persistValidated(User $user, int $pokemonId): PokemonCollectionItem
    {
        return $user->pokemonCollectionItems()->createOrFirst([
            'pokemon_id' => $pokemonId,
        ]);
    }
}
