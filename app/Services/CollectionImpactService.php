<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class CollectionImpactService
{
    /**
     * @var array<string, string>
     */
    private const STAT_LABELS = [
        'hp' => 'HP máximo',
        'attack' => 'Ataque máximo',
        'defense' => 'Defensa máxima',
        'special-attack' => 'Ataque especial máximo',
        'special-defense' => 'Defensa especial máxima',
        'speed' => 'Velocidad máxima',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $collectionItems
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    public function addition(array $collectionItems, array $candidate, bool $isPartial = false): array
    {
        $before = collect($collectionItems)->values();
        $after = $before->concat([$candidate])->values();
        $beforeTypes = $this->typeCounts($before);
        $afterTypes = $this->typeCounts($after);
        $candidateTypes = collect(Arr::get($candidate, 'types', []))
            ->filter(fn (mixed $type): bool => is_string($type))
            ->values();
        $newTypes = $candidateTypes
            ->filter(fn (string $type): bool => ! $beforeTypes->has($type))
            ->values();
        $reinforcedTypes = $candidateTypes
            ->filter(fn (string $type): bool => $beforeTypes->has($type))
            ->values();
        $statChanges = $this->statChanges($before, $after);

        return $this->result(
            mode: 'add',
            status: $before->isEmpty()
                ? 'starts_collection'
                : ($newTypes->isNotEmpty() || $statChanges !== [] ? 'expands' : 'reinforces'),
            before: $before,
            after: $after,
            beforeTypes: $beforeTypes,
            afterTypes: $afterTypes,
            newTypes: $newTypes->all(),
            reinforcedTypes: $reinforcedTypes->all(),
            lostTypes: [],
            statChanges: $statChanges,
            isPartial: $isPartial,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $collectionItems
     * @return array<string, mixed>
     */
    public function removal(array $collectionItems, int $collectionId, bool $isPartial = false): array
    {
        $before = collect($collectionItems)->values();
        $subject = $before->firstWhere('collection_id', $collectionId);

        if (! is_array($subject)) {
            throw new InvalidArgumentException('El Pokémon no pertenece a la colección analizada.');
        }

        $after = $before
            ->reject(fn (array $item): bool => (int) $item['collection_id'] === $collectionId)
            ->values();
        $beforeTypes = $this->typeCounts($before);
        $afterTypes = $this->typeCounts($after);
        $lostTypes = $beforeTypes->keys()
            ->filter(fn (string $type): bool => ! $afterTypes->has($type))
            ->values();
        $statChanges = $this->statChanges($before, $after);

        return $this->result(
            mode: 'remove',
            status: $after->isEmpty()
                ? 'empties_collection'
                : ($lostTypes->isNotEmpty() || $statChanges !== [] ? 'reduces' : 'keeps_coverage'),
            before: $before,
            after: $after,
            beforeTypes: $beforeTypes,
            afterTypes: $afterTypes,
            newTypes: [],
            reinforcedTypes: [],
            lostTypes: $lostTypes->all(),
            statChanges: $statChanges,
            isPartial: $isPartial,
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return Collection<string, int>
     */
    private function typeCounts(Collection $items): Collection
    {
        return $items
            ->flatMap(fn (array $item): array => collect(Arr::get($item, 'types', []))
                ->filter(fn (mixed $type): bool => is_string($type))
                ->values()
                ->all())
            ->countBy();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $before
     * @param  Collection<int, array<string, mixed>>  $after
     * @return list<array{key: string, label: string, before: int|null, after: int|null}>
     */
    private function statChanges(Collection $before, Collection $after): array
    {
        return collect(self::STAT_LABELS)
            ->map(function (string $label, string $stat) use ($before, $after): ?array {
                $beforeMaximum = $this->maximumStat($before, $stat);
                $afterMaximum = $this->maximumStat($after, $stat);

                if ($beforeMaximum === $afterMaximum) {
                    return null;
                }

                return [
                    'key' => $stat,
                    'label' => $label,
                    'before' => $beforeMaximum,
                    'after' => $afterMaximum,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $change): int => abs(($change['after'] ?? 0) - ($change['before'] ?? 0)))
            ->take(3)
            ->values()
            ->all();
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function maximumStat(Collection $items, string $stat): ?int
    {
        $values = $items
            ->map(fn (array $item): mixed => Arr::get($item, "stats.{$stat}"))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value);

        return $values->isEmpty() ? null : $values->max();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $before
     * @param  Collection<int, array<string, mixed>>  $after
     * @param  Collection<string, int>  $beforeTypes
     * @param  Collection<string, int>  $afterTypes
     * @param  list<string>  $newTypes
     * @param  list<string>  $reinforcedTypes
     * @param  list<string>  $lostTypes
     * @param  list<array{key: string, label: string, before: int|null, after: int|null}>  $statChanges
     * @return array<string, mixed>
     */
    private function result(
        string $mode,
        string $status,
        Collection $before,
        Collection $after,
        Collection $beforeTypes,
        Collection $afterTypes,
        array $newTypes,
        array $reinforcedTypes,
        array $lostTypes,
        array $statChanges,
        bool $isPartial,
    ): array {
        return [
            'mode' => $mode,
            'status' => $status,
            'is_partial' => $isPartial,
            'total' => ['before' => $before->count(), 'after' => $after->count()],
            'represented_types' => ['before' => $beforeTypes->count(), 'after' => $afterTypes->count()],
            'new_types' => $newTypes,
            'reinforced_types' => $reinforcedTypes,
            'lost_types' => $lostTypes,
            'stat_changes' => $statChanges,
        ];
    }
}
