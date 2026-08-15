<?php

use App\Http\Controllers\InternalAssistantController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal/assistant')
    ->middleware(['assistant.context', 'throttle:assistant-internal'])
    ->group(function () {
        Route::get('/collection', [InternalAssistantController::class, 'collection']);
        Route::get('/collection/summary', [InternalAssistantController::class, 'collectionSummary']);
        Route::get('/collection/pokemon', [InternalAssistantController::class, 'ownedPokemon']);
        Route::get('/catalog', [InternalAssistantController::class, 'catalog']);
        Route::get('/pokemon', [InternalAssistantController::class, 'pokemon']);
        Route::get('/pokemon/forms', [InternalAssistantController::class, 'forms']);
        Route::get('/pokemon/evolution-chain', [InternalAssistantController::class, 'evolutionChain']);
        Route::get('/pokemon/type-matchups', [InternalAssistantController::class, 'typeMatchups']);
        Route::get('/pokemon/moves', [InternalAssistantController::class, 'moves']);
        Route::get('/move', [InternalAssistantController::class, 'move']);
        Route::post('/compare', [InternalAssistantController::class, 'compare']);
        Route::post('/actions', [InternalAssistantController::class, 'storeAction']);
        Route::post('/actions/{assistantAction}/execute', [InternalAssistantController::class, 'executeAction']);
    });
