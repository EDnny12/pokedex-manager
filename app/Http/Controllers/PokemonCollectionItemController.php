<?php

namespace App\Http\Controllers;

use App\Actions\AddPokemonToCollection;
use App\Contracts\PokemonCatalog;
use App\Exceptions\PokeApiUnavailableException;
use App\Exceptions\PokemonNotFoundException;
use App\Http\Requests\StorePokemonCollectionItemRequest;
use App\Http\Requests\UpdatePokemonCollectionItemRequest;
use App\Models\PokemonCollectionItem;
use App\Services\CollectionImpactService;
use App\Services\PokemonCollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PokemonCollectionItemController extends Controller
{
    public function __construct(
        private PokemonCatalog $pokemonCatalog,
        private PokemonCollectionService $collectionService,
        private CollectionImpactService $impactService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PokemonCollectionItem::class);

        return Inertia::render('Collection/Index', $this->collectionService->forUser($request->user()));
    }

    public function store(
        StorePokemonCollectionItemRequest $request,
        AddPokemonToCollection $addPokemonToCollection,
    ): RedirectResponse {
        try {
            $collectionItem = $addPokemonToCollection->handle(
                $request->user(),
                $request->integer('pokemon_id'),
            );
        } catch (PokemonNotFoundException) {
            return back()->with('error', 'No encontramos ese Pokémon en la Pokédex.');
        } catch (PokeApiUnavailableException) {
            return back()->with('error', 'No pudimos consultar la Pokédex. Inténtalo de nuevo.');
        }

        $message = $collectionItem->wasRecentlyCreated
            ? 'Pokémon agregado a tu colección.'
            : 'Ese Pokémon ya forma parte de tu colección.';

        return back()->with('success', $message);
    }

    public function show(Request $request, PokemonCollectionItem $pokemonCollectionItem): Response
    {
        Gate::authorize('view', $pokemonCollectionItem);

        $apiError = null;

        try {
            $pokemon = $this->pokemonCatalog->find($pokemonCollectionItem->pokemon_id);
        } catch (PokeApiUnavailableException|PokemonNotFoundException) {
            $pokemon = $this->collectionService->fallbackPokemon($pokemonCollectionItem->pokemon_id);
            $apiError = 'No pudimos actualizar la ficha del Pokémon. Aún puedes editar tus datos personales.';
        }

        $collection = $this->collectionService->forUser($request->user());

        return Inertia::render('Collection/Show', [
            'pokemon' => $this->collectionService->merge($pokemonCollectionItem, $pokemon),
            'removalImpact' => $this->impactService->removal(
                $collection['items'],
                $pokemonCollectionItem->getKey(),
                $collection['api_error'] !== null,
            ),
            'apiError' => $apiError,
        ]);
    }

    public function update(
        UpdatePokemonCollectionItemRequest $request,
        PokemonCollectionItem $pokemonCollectionItem,
    ): RedirectResponse {
        $validated = $request->validated();

        foreach (['nickname', 'notes'] as $field) {
            if (! array_key_exists($field, $validated)) {
                continue;
            }

            $validated[$field] = filled($validated[$field]) ? trim($validated[$field]) : null;
        }

        $pokemonCollectionItem->update($validated);

        return back()->with('success', 'Cambios guardados.');
    }

    public function destroy(
        Request $request,
        PokemonCollectionItem $pokemonCollectionItem,
    ): RedirectResponse {
        Gate::authorize('delete', $pokemonCollectionItem);
        $pokemonCollectionItem->delete();

        return to_route('dashboard')->with('success', 'Pokémon eliminado de tu colección.');
    }
}
