<?php

namespace App\Http\Controllers;

use App\Contracts\PokemonCatalog;
use App\Exceptions\PokeApiUnavailableException;
use App\Exceptions\PokemonNotFoundException;
use App\Http\Requests\ComparePokemonRequest;
use Inertia\Inertia;
use Inertia\Response;

class PokemonComparisonController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ComparePokemonRequest $request, PokemonCatalog $pokemonCatalog): Response
    {
        $leftQuery = $request->string('left')->trim()->lower()->toString();
        $rightQuery = $request->string('right')->trim()->lower()->toString();
        $errors = [];
        $left = null;
        $right = null;

        foreach (['left' => $leftQuery, 'right' => $rightQuery] as $side => $query) {
            if ($query === '') {
                continue;
            }

            try {
                ${$side} = $pokemonCatalog->find($query);
            } catch (PokemonNotFoundException) {
                $errors[$side] = 'No encontramos ese Pokémon. Usa su nombre exacto o número.';
            } catch (PokeApiUnavailableException) {
                $errors[$side] = 'No pudimos consultar la Pokédex. Inténtalo de nuevo.';
            }
        }

        return Inertia::render('Compare/Index', [
            'filters' => ['left' => $leftQuery, 'right' => $rightQuery],
            'leftPokemon' => $left,
            'rightPokemon' => $right,
            'comparisonErrors' => $errors,
        ]);
    }
}
