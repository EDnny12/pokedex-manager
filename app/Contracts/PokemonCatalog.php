<?php

namespace App\Contracts;

interface PokemonCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function find(int|string $identifier): array;

    /**
     * @param  iterable<int|string>  $identifiers
     * @return array<int, array<string, mixed>>
     */
    public function findMany(iterable $identifiers): array;

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function browse(string $query, string $type, int $page, int $perPage): array;

    /**
     * @return list<string>
     */
    public function types(): array;
}
