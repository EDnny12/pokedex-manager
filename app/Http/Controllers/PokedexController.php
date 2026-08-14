<?php

namespace App\Http\Controllers;

use App\Contracts\PokemonCatalog;
use App\Exceptions\PokeApiUnavailableException;
use App\Exceptions\PokemonNotFoundException;
use App\Http\Requests\BrowsePokedexRequest;
use App\Services\CollectionImpactService;
use App\Services\PokemonCollectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PokedexController extends Controller
{
    public function __construct(
        private PokemonCatalog $pokemonCatalog,
        private PokemonCollectionService $collectionService,
        private CollectionImpactService $impactService,
    ) {}

    public function index(BrowsePokedexRequest $request): Response
    {
        $query = $request->string('q')->trim()->lower()->toString();
        $type = $request->string('type')->trim()->lower()->toString();
        $page = max(1, $request->integer('page', 1));
        $apiError = null;

        try {
            $catalog = $this->pokemonCatalog->browse($query, $type, $page, 18);
        } catch (PokeApiUnavailableException) {
            $catalog = [
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => 0,
                    'last_page' => 1,
                    'per_page' => 18,
                    'to' => 0,
                    'total' => 0,
                ],
            ];
            $apiError = 'No pudimos cargar la Pokédex. Tu colección no se verá afectada.';
        }

        $collectionByPokemonId = $request->user()
            ->pokemonCollectionItems()
            ->pluck('id', 'pokemon_id');

        $catalog['data'] = collect($catalog['data'])
            ->map(fn (array $pokemon): array => [
                ...$pokemon,
                'collection_id' => $collectionByPokemonId->get($pokemon['id']),
            ])
            ->all();

        return Inertia::render('Pokedex/Index', [
            'catalog' => $catalog,
            'filters' => ['q' => $query, 'type' => $type],
            'types' => $this->pokemonCatalog->types(),
            'apiError' => $apiError,
            'focusSearch' => $request->boolean('focus'),
        ]);
    }

    public function show(Request $request, int $pokemon): Response
    {
        try {
            $pokemonData = $this->pokemonCatalog->find($pokemon);
        } catch (PokemonNotFoundException) {
            abort(404);
        } catch (PokeApiUnavailableException) {
            return Inertia::render('Pokedex/Show', [
                'pokemon' => null,
                'collectionId' => null,
                'additionImpact' => null,
                'apiError' => 'No pudimos cargar la ficha del Pokémon. Inténtalo de nuevo en unos momentos.',
            ]);
        }

        $collectionId = $request->user()
            ->pokemonCollectionItems()
            ->where('pokemon_id', $pokemonData['id'])
            ->value('id');
        $additionImpact = null;

        if ($collectionId === null) {
            $collection = $this->collectionService->forUser($request->user());
            $additionImpact = $this->impactService->addition(
                $collection['items'],
                $pokemonData,
                $collection['api_error'] !== null,
            );
        }

        return Inertia::render('Pokedex/Show', [
            'pokemon' => $pokemonData,
            'collectionId' => $collectionId,
            'additionImpact' => $additionImpact,
            'apiError' => null,
        ]);
    }
}
