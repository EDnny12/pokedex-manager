<?php

namespace Tests\Unit;

use App\Services\CollectionImpactService;
use PHPUnit\Framework\TestCase;

class CollectionImpactServiceTest extends TestCase
{
    public function test_addition_reports_new_types_and_improved_maximum_stats(): void
    {
        $service = new CollectionImpactService;
        $collection = [
            $this->pokemon(1, ['grass', 'poison'], ['speed' => 45, 'special-attack' => 65]),
            $this->pokemon(25, ['electric'], ['speed' => 90, 'special-attack' => 50]),
        ];
        $candidate = $this->pokemon(282, ['psychic', 'fairy'], [
            'speed' => 80,
            'special-attack' => 125,
            'special-defense' => 115,
        ]);

        $impact = $service->addition($collection, $candidate);

        $this->assertSame('add', $impact['mode']);
        $this->assertSame('expands', $impact['status']);
        $this->assertSame(['before' => 2, 'after' => 3], $impact['total']);
        $this->assertSame(['psychic', 'fairy'], $impact['new_types']);
        $changesByStat = array_column($impact['stat_changes'], null, 'key');
        $this->assertSame(125, $changesByStat['special-attack']['after']);
    }

    public function test_removal_reports_types_and_maximum_stats_that_would_be_lost(): void
    {
        $service = new CollectionImpactService;
        $collection = [
            $this->pokemon(25, ['electric'], ['speed' => 90], 10),
            $this->pokemon(1, ['grass', 'poison'], ['speed' => 45], 11),
        ];

        $impact = $service->removal($collection, 10);

        $this->assertSame('remove', $impact['mode']);
        $this->assertSame('reduces', $impact['status']);
        $this->assertSame(['electric'], $impact['lost_types']);
        $this->assertSame([
            'key' => 'speed',
            'label' => 'Velocidad máxima',
            'before' => 90,
            'after' => 45,
        ], $impact['stat_changes'][0]);
    }

    public function test_removing_the_only_pokemon_reports_an_empty_collection(): void
    {
        $service = new CollectionImpactService;
        $collection = [$this->pokemon(25, ['electric'], ['speed' => 90], 10)];

        $impact = $service->removal($collection, 10, true);

        $this->assertSame('empties_collection', $impact['status']);
        $this->assertSame(['before' => 1, 'after' => 0], $impact['total']);
        $this->assertSame(['electric'], $impact['lost_types']);
        $this->assertTrue($impact['is_partial']);
    }

    /**
     * @param  list<string>  $types
     * @param  array<string, int>  $stats
     * @return array<string, mixed>
     */
    private function pokemon(int $pokemonId, array $types, array $stats, ?int $collectionId = null): array
    {
        return [
            'id' => $pokemonId,
            'collection_id' => $collectionId,
            'types' => $types,
            'stats' => $stats,
        ];
    }
}
