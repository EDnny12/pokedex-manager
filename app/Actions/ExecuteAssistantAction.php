<?php

namespace App\Actions;

use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Models\AssistantAction;
use App\Models\User;
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

            if ($lockedAction->type === AssistantActionType::AddPokemon) {
                $collectionItem = $this->addPokemon->handle($user, $pokemonId);
                $result = [
                    'operation' => 'added',
                    'collection_id' => $collectionItem->getKey(),
                    'already_present' => ! $collectionItem->wasRecentlyCreated,
                ];
            } else {
                $deleted = $user->pokemonCollectionItems()->where('pokemon_id', $pokemonId)->delete();
                $result = ['operation' => 'removed', 'was_present' => $deleted > 0];
            }

            $lockedAction->update([
                'status' => AssistantActionStatus::Executed,
                'executed_at' => now(),
                'failure_message' => null,
            ]);

            return ['status' => 'executed', ...$result];
        });
    }
}
