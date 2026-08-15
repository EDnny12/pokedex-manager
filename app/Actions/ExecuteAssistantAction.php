<?php

namespace App\Actions;

use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Exceptions\AssistantUserException;
use App\Models\AssistantAction;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ExecuteAssistantAction
{
    public function __construct(private AddPokemonToCollection $addPokemon) {}

    /** @return array<string, mixed> */
    public function handle(User $user, AssistantAction $action): array
    {
        if ($action->user_id !== $user->getKey()) {
            abort(403);
        }

        $requestedPokemonId = (int) $action->payload['pokemon_id'];
        $validatedPokemonId = $action->status === AssistantActionStatus::Confirmed
            && $action->type === AssistantActionType::AddPokemon
                ? $this->addPokemon->resolvePokemonId($requestedPokemonId)
                : null;

        return DB::transaction(function () use ($user, $action, $requestedPokemonId, $validatedPokemonId): array {
            $lockedAction = AssistantAction::query()->lockForUpdate()->findOrFail($action->getKey());

            if ($lockedAction->user_id !== $user->getKey()) {
                abort(403);
            }

            if ($lockedAction->status === AssistantActionStatus::Executed) {
                return ['status' => 'executed', 'already_executed' => true];
            }

            if ($lockedAction->status !== AssistantActionStatus::Confirmed) {
                throw new AssistantUserException('La acción no está confirmada.');
            }

            $pokemonId = (int) $lockedAction->payload['pokemon_id'];

            if ($pokemonId !== $requestedPokemonId) {
                throw new AssistantUserException('La acción cambió mientras se estaba procesando. Inténtala nuevamente.');
            }

            $result = match ($lockedAction->type) {
                AssistantActionType::AddPokemon => $this->add($user, $validatedPokemonId ?? $pokemonId),
                AssistantActionType::RemovePokemon => $this->remove($user, $pokemonId),
                AssistantActionType::UpdatePokemon => $this->update($user, $pokemonId, $lockedAction->payload),
            };

            $lockedAction->update([
                'status' => AssistantActionStatus::Executed,
                'executed_at' => now(),
                'failure_message' => null,
            ]);

            return ['status' => 'executed', ...$result];
        }, attempts: 3);
    }

    /** @return array<string, mixed> */
    private function add(User $user, int $pokemonId): array
    {
        $collectionItem = $this->addPokemon->persistValidated($user, $pokemonId);

        return [
            'operation' => 'added',
            'collection_id' => $collectionItem->getKey(),
            'already_present' => ! $collectionItem->wasRecentlyCreated,
        ];
    }

    /** @return array<string, mixed> */
    private function remove(User $user, int $pokemonId): array
    {
        $deleted = $user->pokemonCollectionItems()->where('pokemon_id', $pokemonId)->delete();

        return ['operation' => 'removed', 'was_present' => $deleted > 0];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function update(User $user, int $pokemonId, array $payload): array
    {
        $collectionItem = $user->pokemonCollectionItems()
            ->where('pokemon_id', $pokemonId)
            ->lockForUpdate()
            ->first();

        if ($collectionItem === null) {
            throw new AssistantUserException('Ese Pokémon ya no forma parte de tu colección.');
        }

        $changes = Arr::only((array) ($payload['changes'] ?? []), ['nickname', 'notes', 'is_favorite']);

        if ($changes === []) {
            throw new AssistantUserException('La acción no contiene cambios válidos.');
        }

        $collectionItem->update($changes);

        return [
            'operation' => 'updated',
            'collection_id' => $collectionItem->getKey(),
            'changed_fields' => array_keys($changes),
        ];
    }
}
