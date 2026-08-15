<?php

namespace App\Services;

use App\Contracts\PokemonCatalog;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TrainerCardService
{
    public function __construct(
        private PokemonCollectionService $collectionService,
        private CollectionInsightService $insightService,
        private PokemonCatalog $pokemonCatalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, bool $forceRefreshBio = false): array
    {
        $hydrated = $this->collectionService->forUser($user);
        $items = $hydrated['items'];
        $types = $this->pokemonCatalog->types();
        $insights = $this->insightService->calculate($items, $types);

        $total = (int) $insights['total'];
        $favorites = (int) $insights['favorites'];
        $dominantType = $insights['dominant_type']['name'] ?? null;
        $rank = $this->calculateRank($total);

        $party = $this->calculateParty($items);
        $signature = $this->calculateSignaturePokemon($items);

        $bio = $this->resolveTrainerBio(
            $user,
            $items,
            $rank,
            $total,
            $favorites,
            $dominantType,
            $signature,
            $party,
            $forceRefreshBio,
        );

        return [
            'trainer_number' => sprintf('TR-%05d', $user->id),
            'trainer_name' => $user->name,
            'avatar_url' => $user->profile_photo_url,
            'member_since' => $user->created_at?->translatedFormat('d M Y') ?? 'Reciente',
            'rank' => $rank,
            'total_pokemon' => $total,
            'favorites_count' => $favorites,
            'dominant_type' => $dominantType,
            'signature_pokemon' => $signature,
            'party' => $party,
            'headline' => $bio['headline'],
            'description' => $bio['description'],
            'is_ai_generated' => $bio['is_ai_generated'] ?? false,
        ];
    }

    public function calculateRank(int $total): string
    {
        return match (true) {
            $total >= 60 => 'Maestro Pokémon',
            $total >= 30 => 'Líder',
            $total >= 15 => 'Experto',
            $total >= 5 => 'Entrenador',
            default => 'Novato',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function calculateParty(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        return collect($items)
            ->sort(fn (array $a, array $b): int => $this->comparePokemonForTrainerCard($a, $b))
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>|null
     */
    public function calculateSignaturePokemon(array $items): ?array
    {
        if (empty($items)) {
            return null;
        }

        return collect($items)
            ->sort(fn (array $a, array $b): int => $this->comparePokemonForTrainerCard($a, $b))
            ->first();
    }

    /**
     * Deterministic comparison for Trainer Card party and signature Pokémon:
     * 1. Favorites first
     * 2. Highest Base Stat Total (BST)
     * 3. Lowest ID as tie-breaker
     *
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    public function comparePokemonForTrainerCard(array $a, array $b): int
    {
        $favA = ! empty($a['is_favorite']) ? 1 : 0;
        $favB = ! empty($b['is_favorite']) ? 1 : 0;

        if ($favA !== $favB) {
            return $favB <=> $favA;
        }

        $bstA = $this->baseStatTotal($a);
        $bstB = $this->baseStatTotal($b);

        if ($bstA !== $bstB) {
            return $bstB <=> $bstA;
        }

        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    }

    /**
     * @param  array<string, mixed>  $pokemon
     */
    public function baseStatTotal(array $pokemon): int
    {
        $stats = $pokemon['stats'] ?? [];

        return is_array($stats) ? (int) array_sum($stats) : 0;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>|null  $signature
     * @param  list<array<string, mixed>>  $party
     * @return array{headline: string, description: string, is_ai_generated: bool}
     */
    private function resolveTrainerBio(
        User $user,
        array $items,
        string $rank,
        int $total,
        int $favorites,
        ?string $dominantType,
        ?array $signature,
        array $party,
        bool $forceRefresh,
    ): array {
        if ($total === 0) {
            return [
                'headline' => 'Entrenador en formación',
                'description' => 'Aún no has registrado Pokémon en tu colección. ¡Explora la Pokédex para comenzar tu aventura!',
                'is_ai_generated' => false,
            ];
        }

        $collectionHash = hash('sha256', json_encode([
            'rank' => $rank,
            'total' => $total,
            'favorites' => $favorites,
            'dominant_type' => $dominantType,
            'signature_id' => $signature['id'] ?? null,
            'party' => array_map(fn (array $poke): array => [
                'id' => $poke['id'],
                'is_favorite' => (bool) ($poke['is_favorite'] ?? false),
            ], $party),
        ], JSON_THROW_ON_ERROR));

        $cacheKey = "trainer:bio:{$user->id}:{$collectionHash}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($rank, $total, $favorites, $dominantType, $signature, $party): array {
            return $this->requestAiTrainerBio($rank, $total, $favorites, $dominantType, $signature, $party)
                ?? $this->fallbackTrainerBio($rank, $total, $dominantType, $signature);
        });
    }

    /**
     * @param  array<string, mixed>|null  $signature
     * @param  list<array<string, mixed>>  $party
     * @return array{headline: string, description: string, is_ai_generated: bool}|null
     */
    private function requestAiTrainerBio(
        string $rank,
        int $total,
        int $favorites,
        ?string $dominantType,
        ?array $signature,
        array $party,
    ): ?array {
        try {
            $response = Http::baseUrl((string) config('services.assistant.agent_url'))
                ->acceptJson()
                ->asJson()
                ->withToken((string) config('services.assistant.service_secret'))
                ->connectTimeout(2)
                ->timeout(8)
                ->post('/trainer/profile-bio', [
                    'rank' => $rank,
                    'totalPokemon' => $total,
                    'favorites' => $favorites,
                    'dominantType' => $dominantType,
                    'signaturePokemon' => $signature ? [
                        'name' => $signature['name'],
                        'displayName' => $signature['display_name'],
                        'types' => $signature['types'],
                        'isFavorite' => (bool) ($signature['is_favorite'] ?? false),
                    ] : null,
                    'party' => array_map(fn (array $poke): array => [
                        'name' => $poke['name'],
                        'displayName' => $poke['display_name'],
                        'types' => $poke['types'],
                        'isFavorite' => (bool) ($poke['is_favorite'] ?? false),
                    ], $party),
                ]);

            if ($response->successful()) {
                $headline = trim((string) $response->json('headline'));
                $description = trim((string) $response->json('description'));

                if ($headline !== '' && $description !== '') {
                    return [
                        'headline' => $headline,
                        'description' => $description,
                        'is_ai_generated' => true,
                    ];
                }
            }
        } catch (ConnectionException) {
            // Service offline or timeout - fallback gracefully
        } catch (\Throwable) {
            // Parse or network failure - fallback gracefully
        }

        return null;
    }

    /**
     * Deterministic narrative fallback when AI service is unavailable
     *
     * @param  array<string, mixed>|null  $signature
     * @return array{headline: string, description: string, is_ai_generated: bool}
     */
    public function fallbackTrainerBio(
        string $rank,
        int $total,
        ?string $dominantType,
        ?array $signature,
    ): array {
        if ($dominantType) {
            $typeLabel = Str::title($dominantType);

            return [
                'headline' => "Especialista en tipo {$typeLabel}",
                'description' => $signature
                    ? "Entrenador de rango {$rank} con afinidad al tipo {$typeLabel}, guiado por su {$signature['display_name']}."
                    : "Entrenador de rango {$rank} con una colección enfocada en Pokémon de tipo {$typeLabel}.",
                'is_ai_generated' => false,
            ];
        }

        return [
            'headline' => "Entrenador {$rank}",
            'description' => $signature
                ? "Entrenador con una colección equilibrada de {$total} Pokémon, liderada por {$signature['display_name']}."
                : "Entrenador con una colección equilibrada de {$total} Pokémon registrados.",
            'is_ai_generated' => false,
        ];
    }
}
