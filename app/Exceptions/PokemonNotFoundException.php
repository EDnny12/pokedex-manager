<?php

namespace App\Exceptions;

use Exception;

class PokemonNotFoundException extends Exception
{
    public function __construct(public readonly int|string $identifier)
    {
        parent::__construct("Pokémon [{$identifier}] was not found.");
    }
}
