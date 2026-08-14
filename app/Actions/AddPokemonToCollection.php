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
        $pokemon = $this->pokemonCatalog->find($pokemonId);

        return $user->pokemonCollectionItems()->createOrFirst([
            'pokemon_id' => $pokemon['id'],
        ]);
    }
}
