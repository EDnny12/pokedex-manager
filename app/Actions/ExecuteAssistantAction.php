<?php

namespace App\Actions;

use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Models\AssistantAction;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExecuteAssistantAction
{
    public function __construct(private AddPokemonToCollection $addPokemon) {}

    /** @return array<string, mixed> */
    public function handle(User $user, AssistantAction $action): array
    {
        return DB::transaction(function () use ($user, $action): array {
            $lockedAction = AssistantAction::query()->lockForUpdate()->findOrFail($action->getKey());

            if ($lockedAction->user_id !== $user->getKey()) {
                abort(403);
            }

            if ($lockedAction->status === AssistantActionStatus::Executed) {
                return ['status' => 'executed', 'already_executed' => true];
            }

            if ($lockedAction->status !== AssistantActionStatus::Confirmed) {
                throw new RuntimeException('La acción no está confirmada.');
            }

            $pokemonId = (int) $lockedAction->payload['pokemon_id'];

            $result = match ($lockedAction->type) {
                AssistantActionType::AddPokemon => $this->add($user, $pokemonId),
                AssistantActionType::RemovePokemon => $this->remove($user, $pokemonId),
                AssistantActionType::UpdatePokemon => $this->update($user, $pokemonId, $lockedAction->payload),
            };

            $lockedAction->update([
                'status' => AssistantActionStatus::Executed,
                'executed_at' => now(),
                'failure_message' => null,
            ]);

            return ['status' => 'executed', ...$result];
        });
    }

    /** @return array<string, mixed> */
    private function add(User $user, int $pokemonId): array
    {
        $collectionItem = $this->addPokemon->handle($user, $pokemonId);

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
            throw new RuntimeException('Ese Pokémon ya no forma parte de tu colección.');
        }

        $changes = Arr::only((array) ($payload['changes'] ?? []), ['nickname', 'notes', 'is_favorite']);

        if ($changes === []) {
            throw new RuntimeException('La acción no contiene cambios válidos.');
        }

        $collectionItem->update($changes);

        return [
            'operation' => 'updated',
            'collection_id' => $collectionItem->getKey(),
            'changed_fields' => array_keys($changes),
        ];
    }
}
