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
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function search(string $query, string $type, string $ability, string $generation, int $limit): array;

    /** @return array<string, mixed> */
    public function forms(int|string $identifier): array;

    /** @return array<string, mixed> */
    public function evolutionChain(int|string $identifier): array;

    /** @return array<string, mixed> */
    public function typeMatchups(int|string $identifier): array;

    /** @return array<string, mixed> */
    public function moves(int|string $identifier, string $learnMethod, string $versionGroup, int $limit): array;

    /** @return array<string, mixed> */
    public function move(int|string $identifier): array;

    /**
     * @return list<string>
     */
    public function types(): array;
}
