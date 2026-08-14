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
            ->countBy()
            ->sortDesc();

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

        $dominantType = $typeDistribution->isEmpty()
            ? null
            : ['name' => $typeDistribution->keys()->first(), 'count' => $typeDistribution->first()];

        return [
            'total' => $items->count(),
            'favorites' => $items->where('is_favorite', true)->count(),
            'represented_types' => $typeDistribution->count(),
            'total_types' => count($allTypes),
            'type_distribution' => $typeDistribution
                ->map(fn (int $count, string $type): array => ['name' => $type, 'count' => $count])
                ->values()
                ->all(),
            'dominant_type' => $dominantType,
            'missing_types' => collect($allTypes)->diff($typeDistribution->keys())->values()->all(),
            'top_stats' => $topStats,
        ];
    }
}
