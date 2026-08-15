<?php

namespace App\Actions;

use App\Contracts\AssistantAgent;
use App\Enums\AssistantActionStatus;
use App\Models\AssistantAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ConfirmAssistantAction
{
    public function __construct(private AssistantAgent $assistantAgent) {}

    /** @return array<string, mixed> */
    public function handle(User $user, AssistantAction $action, string $requestId): array
    {
        $action = DB::transaction(function () use ($user, $action): AssistantAction {
            $lockedAction = AssistantAction::query()->lockForUpdate()->findOrFail($action->getKey());

            if ($lockedAction->user_id !== $user->getKey()) {
                abort(403);
            }

            if ($lockedAction->status === AssistantActionStatus::Executed) {
                return $lockedAction;
            }

            if ($lockedAction->status !== AssistantActionStatus::Pending) {
                throw new RuntimeException('Esta acción ya no se puede confirmar.');
            }

            if ($lockedAction->expires_at->isPast()) {
                $lockedAction->update(['status' => AssistantActionStatus::Expired]);

                return $lockedAction;
            }

            $lockedAction->update(['status' => AssistantActionStatus::Confirmed]);

            return $lockedAction->fresh('conversation');
        }, attempts: 3);

        if ($action->status === AssistantActionStatus::Expired) {
            throw new RuntimeException('La confirmación expiró. Solicita la acción nuevamente.');
        }

        if ($action->status === AssistantActionStatus::Executed) {
            return ['action' => $action, 'result' => ['already_executed' => true]];
        }

        try {
            $result = $this->assistantAgent->execute($user, $action, $requestId);
        } catch (Throwable $exception) {
            DB::transaction(function () use ($action): void {
                $lockedAction = AssistantAction::query()->lockForUpdate()->findOrFail($action->getKey());

                if ($lockedAction->status === AssistantActionStatus::Confirmed) {
                    $lockedAction->update([
                        'status' => AssistantActionStatus::Failed,
                        'failure_message' => 'No se pudo completar la acción.',
                    ]);
                }
            }, attempts: 3);

            throw $exception;
        }

        return ['action' => $action->fresh(), 'result' => $result];
    }
}
