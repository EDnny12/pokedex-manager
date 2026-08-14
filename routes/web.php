<?php

use App\Http\Controllers\AssistantActionController;
use App\Http\Controllers\AssistantAttachmentController;
use App\Http\Controllers\AssistantConversationController;
use App\Http\Controllers\AssistantMessageController;
use App\Http\Controllers\InsightController;
use App\Http\Controllers\PokedexController;
use App\Http\Controllers\PokemonCollectionItemController;
use App\Http\Controllers\PokemonComparisonController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', [PokemonCollectionItemController::class, 'index'])->name('dashboard');
    Route::post('/collection', [PokemonCollectionItemController::class, 'store'])->name('collection.store');
    Route::get('/collection/{pokemonCollectionItem}', [PokemonCollectionItemController::class, 'show'])->name('collection.show');
    Route::patch('/collection/{pokemonCollectionItem}', [PokemonCollectionItemController::class, 'update'])->name('collection.update');
    Route::delete('/collection/{pokemonCollectionItem}', [PokemonCollectionItemController::class, 'destroy'])->name('collection.destroy');

    Route::get('/pokedex', [PokedexController::class, 'index'])->name('pokedex.index');
    Route::get('/pokedex/{pokemon}', [PokedexController::class, 'show'])
        ->whereNumber('pokemon')
        ->name('pokedex.show');

    Route::get('/insights', InsightController::class)->name('insights.index');
    Route::get('/compare', PokemonComparisonController::class)->name('compare.index');

    Route::get('/assistant/conversations', [AssistantConversationController::class, 'index'])
        ->middleware('throttle:assistant-read')
        ->name('assistant.conversations.index');
    Route::post('/assistant/conversations', [AssistantConversationController::class, 'store'])
        ->middleware('throttle:assistant-write')
        ->name('assistant.conversations.store');
    Route::delete('/assistant/conversations/{assistantConversation}', [AssistantConversationController::class, 'destroy'])
        ->middleware('throttle:assistant-write')
        ->name('assistant.conversations.destroy');
    Route::post('/assistant/conversations/{assistantConversation}/messages', [AssistantMessageController::class, 'store'])
        ->middleware('throttle:assistant-chat')
        ->name('assistant.messages.store');
    Route::get('/assistant/attachments/{assistantMessageAttachment}', [AssistantAttachmentController::class, 'show'])
        ->middleware('throttle:assistant-read')
        ->name('assistant.attachments.show');
    Route::post('/assistant/actions/{assistantAction}/confirm', [AssistantActionController::class, 'confirm'])
        ->middleware('throttle:assistant-write')
        ->name('assistant.actions.confirm');
    Route::post('/assistant/actions/{assistantAction}/cancel', [AssistantActionController::class, 'cancel'])
        ->middleware('throttle:assistant-write')
        ->name('assistant.actions.cancel');
});
