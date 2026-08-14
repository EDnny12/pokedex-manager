<?php

namespace App\Actions;

use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\AssistantToolService;

class CreateAssistantAction
{
    public function __construct(private AssistantToolService $tools) {}

    public function handle(
        User $user,
        AssistantConversation $conversation,
        AssistantActionType $type,
        int|string $pokemon,
    ): AssistantAction {
        $pokemonData = $this->tools->pokemon($pokemon);
        $ownedPokemon = $user->pokemonCollectionItems()->where('pokemon_id', $pokemonData['id'])->exists();

        if ($type === AssistantActionType::AddPokemon && $ownedPokemon) {
            throw new \RuntimeException('Ese Pokémon ya forma parte de tu colección.');
        }

        if ($type === AssistantActionType::RemovePokemon && ! $ownedPokemon) {
            throw new \RuntimeException('Ese Pokémon no forma parte de tu colección.');
        }

        $existingAction = $conversation->actions()
            ->where('type', $type)
            ->where('status', AssistantActionStatus::Pending)
            ->where('expires_at', '>', now())
            ->where('payload->pokemon_id', $pokemonData['id'])
            ->latest()
            ->first();

        if ($existingAction instanceof AssistantAction) {
            return $existingAction;
        }

        $action = new AssistantAction([
            'type' => $type,
            'payload' => [
                'pokemon_id' => $pokemonData['id'],
                'display_name' => $pokemonData['display_name'],
                'image' => $pokemonData['image'],
            ],
            'status' => AssistantActionStatus::Pending,
            'expires_at' => now()->addMinutes((int) config('services.assistant.action_ttl', 15)),
        ]);
        $action->user()->associate($user);
        $conversation->actions()->save($action);

        return $action;
    }
}
