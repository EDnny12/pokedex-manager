<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\CollectionInsightService;
use App\Services\TrainerCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrainerCardServiceTest extends TestCase
{
    use RefreshDatabase;

    private TrainerCardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TrainerCardService::class);
    }

    public function test_calculate_rank_is_deterministic_and_matches_thresholds(): void
    {
        $this->assertSame('Novato', $this->service->calculateRank(0));
        $this->assertSame('Novato', $this->service->calculateRank(4));
        $this->assertSame('Entrenador', $this->service->calculateRank(5));
        $this->assertSame('Entrenador', $this->service->calculateRank(14));
        $this->assertSame('Experto', $this->service->calculateRank(15));
        $this->assertSame('Experto', $this->service->calculateRank(29));
        $this->assertSame('Líder', $this->service->calculateRank(30));
        $this->assertSame('Líder', $this->service->calculateRank(59));
        $this->assertSame('Maestro Pokémon', $this->service->calculateRank(60));
        $this->assertSame('Maestro Pokémon', $this->service->calculateRank(150));
    }

    public function test_signature_pokemon_prioritizes_favorites_with_highest_bst_and_breaks_ties_by_id(): void
    {
        $pikachu = $this->pokemonData(25, 'Pikachu', 320, isFavorite: false);
        $charizard = $this->pokemonData(6, 'Charizard', 534, isFavorite: false);
        $blastoise = $this->pokemonData(9, 'Blastoise', 530, isFavorite: true);
        $venusaur = $this->pokemonData(3, 'Venusaur', 525, isFavorite: true);

        // Even though Charizard has higher BST (534), Blastoise is favorite with highest BST (530)
        $signature = $this->service->calculateSignaturePokemon([$pikachu, $charizard, $blastoise, $venusaur]);
        $this->assertSame(9, $signature['id']);

        // Without favorites, Charizard wins by BST
        $blastoise['is_favorite'] = false;
        $venusaur['is_favorite'] = false;
        $signatureNoFavs = $this->service->calculateSignaturePokemon([$pikachu, $charizard, $blastoise, $venusaur]);
        $this->assertSame(6, $signatureNoFavs['id']);

        // Tie-breaker by lower ID when BST is identical
        $tiedA = $this->pokemonData(10, 'TiedA', 500, isFavorite: false);
        $tiedB = $this->pokemonData(5, 'TiedB', 500, isFavorite: false);
        $signatureTied = $this->service->calculateSignaturePokemon([$tiedA, $tiedB]);
        $this->assertSame(5, $signatureTied['id']);
    }

    public function test_party_selects_up_to_six_pokemon_strictly_by_favorite_then_bst_then_id(): void
    {
        $items = [
            $this->pokemonData(1, 'P1', 300, isFavorite: false),
            $this->pokemonData(2, 'P2', 600, isFavorite: false),
            $this->pokemonData(3, 'P3', 400, isFavorite: true),
            $this->pokemonData(4, 'P4', 500, isFavorite: true),
            $this->pokemonData(5, 'P5', 200, isFavorite: false),
            $this->pokemonData(6, 'P6', 700, isFavorite: false),
            $this->pokemonData(7, 'P7', 100, isFavorite: false),
        ];

        $party = $this->service->calculateParty($items);

        $this->assertCount(6, $party);
        // Expect favorites first (P4 [500], P3 [400]), then highest BST (P6 [700], P2 [600], P1 [300], P5 [200])
        $this->assertSame([4, 3, 6, 2, 1, 5], array_column($party, 'id'));
    }

    public function test_empty_collection_returns_default_rank_and_empty_party(): void
    {
        $user = User::factory()->create(['id' => 42, 'name' => 'Ash Ketchum']);

        $card = $this->service->forUser($user);

        $this->assertSame('TR-00042', $card['trainer_number']);
        $this->assertSame('Ash Ketchum', $card['trainer_name']);
        $this->assertSame('Novato', $card['rank']);
        $this->assertSame(0, $card['total_pokemon']);
        $this->assertSame(0, $card['favorites_count']);
        $this->assertNull($card['dominant_type']);
        $this->assertNull($card['signature_pokemon']);
        $this->assertSame([], $card['party']);
        $this->assertSame('Entrenador en formación', $card['headline']);
        $this->assertFalse($card['is_ai_generated']);
    }

    public function test_deterministic_fallback_bio_when_ai_is_unavailable(): void
    {
        $signature = $this->pokemonData(6, 'Charizard', 534, isFavorite: true);
        $bioWithType = $this->service->fallbackTrainerBio('Experto', 18, 'fire', $signature);

        $this->assertSame('Especialista en tipo Fire', $bioWithType['headline']);
        $this->assertStringContainsString('Charizard', $bioWithType['description']);
        $this->assertFalse($bioWithType['is_ai_generated']);

        $bioWithoutType = $this->service->fallbackTrainerBio('Líder', 35, null, $signature);
        $this->assertSame('Entrenador Líder', $bioWithoutType['headline']);
        $this->assertStringContainsString('colección equilibrada de 35 Pokémon', $bioWithoutType['description']);
    }

    public function test_dominant_type_tie_break_is_deterministic(): void
    {
        $insightService = app(CollectionInsightService::class);

        // Equal counts for 'water' and 'fire'
        $pokemonA = ['id' => 1, 'collection_id' => 1, 'types' => ['water'], 'display_name' => 'A', 'nickname' => null, 'is_favorite' => false, 'stats' => []];
        $pokemonB = ['id' => 2, 'collection_id' => 2, 'types' => ['fire'], 'display_name' => 'B', 'nickname' => null, 'is_favorite' => false, 'stats' => []];

        $insights = $insightService->calculate([$pokemonA, $pokemonB], ['water', 'fire']);

        // 'fire' comes before 'water' alphabetically
        $this->assertSame('fire', $insights['dominant_type']['name']);
        $this->assertSame(1, $insights['dominant_type']['count']);
    }

    /**
     * @return array<string, mixed>
     */
    private function pokemonData(int $id, string $name, int $totalStats, bool $isFavorite = false): array
    {
        return [
            'id' => $id,
            'collection_id' => $id,
            'name' => strtolower($name),
            'display_name' => $name,
            'image' => "https://example.com/{$id}.png",
            'types' => ['fire'],
            'height_m' => 1.5,
            'weight_kg' => 50.0,
            'abilities' => [],
            'stats' => [
                'hp' => (int) ($totalStats / 6),
                'attack' => (int) ($totalStats / 6),
                'defense' => (int) ($totalStats / 6),
                'special-attack' => (int) ($totalStats / 6),
                'special-defense' => (int) ($totalStats / 6),
                'speed' => $totalStats - 5 * (int) ($totalStats / 6),
            ],
            'nickname' => null,
            'notes' => null,
            'is_favorite' => $isFavorite,
            'added_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
