<?php

namespace App\Http\Controllers;

use App\Contracts\PokemonCatalog;
use App\Services\CollectionInsightService;
use App\Services\PokemonCollectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InsightController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        PokemonCollectionService $collectionService,
        CollectionInsightService $insightService,
        PokemonCatalog $pokemonCatalog,
    ): Response {
        $collection = $collectionService->forUser($request->user());

        return Inertia::render('Insights/Index', [
            'insights' => $insightService->calculate($collection['items'], $pokemonCatalog->types()),
            'apiError' => $collection['api_error'],
        ]);
    }
}
