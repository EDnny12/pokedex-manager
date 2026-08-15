<?php

namespace App\Services;

class CollectionInsightService
{
    /**
     * @var array<string, string>
     */
    private const STAT_LABELS = [
        'hp' => 'Mayor HP',
        'attack' => 'Mayor ataque',
        'defense' => 'Mayor defensa',
        'special-attack' => 'Mayor ataque especial',
        'special-defense' => 'Mayor defensa especial',
        'speed' => 'Más rápido',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $collectionItems
     * @param  list<string>  $allTypes
     * @return array<string, mixed>
     */
    public function calculate(array $collectionItems, array $allTypes): array
    {
        $items = collect($collectionItems);
        $typeDistribution = $items
            ->flatMap(fn (array $pokemon): array => $pokemon['types'])
            ->countBy();

        $sortedTypeKeys = $typeDistribution->keys()->sort(function (string $typeA, string $typeB) use ($typeDistribution): int {
            $countA = (int) $typeDistribution[$typeA];
            $countB = (int) $typeDistribution[$typeB];

            if ($countA !== $countB) {
                return $countB <=> $countA;
            }

            return strcmp($typeA, $typeB);
        })->values();

        $sortedTypeDistribution = $sortedTypeKeys->mapWithKeys(
            fn (string $type): array => [$type => $typeDistribution[$type]]
        );

        $topStats = collect(self::STAT_LABELS)
            ->map(function (string $label, string $stat) use ($items): ?array {
                $pokemon = $items
                    ->filter(fn (array $item): bool => isset($item['stats'][$stat]))
                    ->sortByDesc(fn (array $item): int => $item['stats'][$stat])
                    ->first();

                if ($pokemon === null) {
                    return null;
                }

                return [
                    'key' => $stat,
                    'label' => $label,
                    'pokemon_name' => $pokemon['nickname'] ?: $pokemon['display_name'],
                    'pokemon_id' => $pokemon['id'],
                    'collection_id' => $pokemon['collection_id'],
                    'value' => $pokemon['stats'][$stat],
                ];
            })
            ->filter()
            ->values()
            ->all();

        $dominantType = $sortedTypeDistribution->isEmpty()
            ? null
            : ['name' => (string) $sortedTypeDistribution->keys()->first(), 'count' => (int) $sortedTypeDistribution->first()];

        return [
            'total' => $items->count(),
            'favorites' => $items->where('is_favorite', true)->count(),
            'represented_types' => $sortedTypeDistribution->count(),
            'total_types' => count($allTypes),
            'type_distribution' => $sortedTypeDistribution
                ->map(fn (int $count, string $type): array => ['name' => $type, 'count' => $count])
                ->values()
                ->all(),
            'dominant_type' => $dominantType,
            'missing_types' => collect($allTypes)->diff($sortedTypeDistribution->keys())->values()->all(),
            'top_stats' => $topStats,
        ];
    }
}
