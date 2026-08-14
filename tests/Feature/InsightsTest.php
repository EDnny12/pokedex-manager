<?php

namespace Tests\Feature;

use App\Contracts\PokemonCatalog;
use App\Models\PokemonCollectionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_are_calculated_from_users_collection(): void
    {
        $user = User::factory()->create();
        PokemonCollectionItem::factory()->for($user)->create([
            'pokemon_id' => 25,
            'is_favorite' => true,
        ]);

        $pokemon = [
            'id' => 25,
            'name' => 'pikachu',
            'display_name' => 'Pikachu',
            'image' => null,
            'types' => ['electric'],
            'height_m' => 0.4,
            'weight_kg' => 6.0,
            'abilities' => [],
            'stats' => ['hp' => 35, 'speed' => 90],
        ];

        $this->mock(PokemonCatalog::class, function (MockInterface $mock) use ($pokemon): void {
            $mock->shouldReceive('findMany')->once()->andReturn([$pokemon]);
            $mock->shouldReceive('types')->once()->andReturn(['normal', 'electric', 'fire']);
        });

        $response = $this->actingAs($user)->get(route('insights.index'));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Insights/Index')
            ->where('insights.total', 1)
            ->where('insights.favorites', 1)
            ->where('insights.represented_types', 1)
            ->where('insights.dominant_type.name', 'electric')
            ->where('insights.missing_types', ['normal', 'fire']));
    }
}
