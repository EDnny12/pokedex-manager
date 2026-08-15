<?php

namespace App\Actions;

use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Exceptions\AssistantUserException;
use App\Exceptions\PokemonNotFoundException;
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
        array $changes = [],
    ): AssistantAction {
        if ($type === AssistantActionType::AddPokemon) {
            $pokemonData = $this->tools->pokemon($pokemon);

            if ($user->pokemonCollectionItems()->where('pokemon_id', $pokemonData['id'])->exists()) {
                throw new AssistantUserException('Ese Pokémon ya forma parte de tu colección.');
            }
        } else {
            try {
                $pokemonData = $this->tools->ownedPokemon($user, $pokemon);
            } catch (PokemonNotFoundException) {
                throw new AssistantUserException('Ese Pokémon no forma parte de tu colección.');
            }
        }

        $normalizedChanges = $type === AssistantActionType::UpdatePokemon
            ? $this->normalizeChanges($changes)
            : [];

        if ($type === AssistantActionType::UpdatePokemon && ! $this->changesCollectionData($pokemonData, $normalizedChanges)) {
            throw new AssistantUserException('Esos datos ya están guardados en tu colección.');
        }

        $existingActionQuery = $conversation->actions()
            ->where('type', $type)
            ->where('status', AssistantActionStatus::Pending)
            ->where('expires_at', '>', now())
            ->where('payload->pokemon_id', $pokemonData['id'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $existingAction = $type === AssistantActionType::UpdatePokemon
            ? $existingActionQuery
                ->get()
                ->first(fn (AssistantAction $action): bool => ($action->payload['changes'] ?? []) === $normalizedChanges)
            : $existingActionQuery->first();

        if ($existingAction instanceof AssistantAction) {
            return $existingAction;
        }

        $payload = [
            'pokemon_id' => $pokemonData['id'],
            'display_name' => $pokemonData['display_name'],
            'image' => $pokemonData['image'],
        ];

        if ($type === AssistantActionType::UpdatePokemon) {
            $payload['changes'] = $normalizedChanges;
        }

        $action = new AssistantAction([
            'type' => $type,
            'payload' => $payload,
            'status' => AssistantActionStatus::Pending,
            'expires_at' => now()->addMinutes((int) config('services.assistant.action_ttl', 15)),
        ]);
        $action->user()->associate($user);
        $conversation->actions()->save($action);

        return $action;
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, string|bool|null>
     */
    private function normalizeChanges(array $changes): array
    {
        $normalized = [];

        foreach (['nickname', 'notes'] as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            $value = $changes[$field];
            $normalized[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        if (array_key_exists('is_favorite', $changes)) {
            $normalized['is_favorite'] = (bool) $changes['is_favorite'];
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $pokemonData
     * @param  array<string, string|bool|null>  $changes
     */
    private function changesCollectionData(array $pokemonData, array $changes): bool
    {
        foreach ($changes as $field => $value) {
            if (($pokemonData[$field] ?? null) !== $value) {
                return true;
            }
        }

        return false;
    }
}
