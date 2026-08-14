<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class PokeApiUnavailableException extends Exception
{
    public function __construct(public readonly string $endpoint = '', ?Throwable $previous = null)
    {
        parent::__construct('El servicio de la Pokédex no está disponible temporalmente.', previous: $previous);
    }

    /**
     * @return array<string, string>
     */
    public function context(): array
    {
        return ['pokeapi_endpoint' => $this->endpoint];
    }
}
