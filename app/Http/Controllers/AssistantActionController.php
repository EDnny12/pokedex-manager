<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmAssistantAction;
use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use App\Http\Resources\AssistantActionResource;
use App\Models\AssistantAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AssistantActionController extends Controller
{
    public function confirm(
        Request $request,
        AssistantAction $assistantAction,
        ConfirmAssistantAction $confirm,
    ): JsonResponse {
        Gate::authorize('update', $assistantAction);

        try {
            $result = $confirm->handle($request->user(), $assistantAction, Str::uuid()->toString());
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'No pudimos completar la acción. Inténtalo de nuevo.',
            ], 422);
        }

        return response()->json([
            'message' => match ($result['action']->type) {
                AssistantActionType::AddPokemon => 'Pokémon agregado a tu colección.',
                AssistantActionType::RemovePokemon => 'Pokémon eliminado de tu colección.',
                AssistantActionType::UpdatePokemon => 'Cambios guardados en tu colección.',
            },
            'action' => (new AssistantActionResource($result['action']))->resolve(),
            'result' => $result['result'],
        ]);
    }

    public function cancel(Request $request, AssistantAction $assistantAction): JsonResponse
    {
        Gate::authorize('update', $assistantAction);

        try {
            $action = DB::transaction(function () use ($assistantAction): AssistantAction {
                $lockedAction = AssistantAction::query()->lockForUpdate()->findOrFail($assistantAction->getKey());

                if ($lockedAction->status !== AssistantActionStatus::Pending) {
                    throw new RuntimeException('Esta acción ya no se puede cancelar.');
                }

                $lockedAction->update(['status' => AssistantActionStatus::Cancelled]);

                return $lockedAction;
            }, attempts: 3);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Acción cancelada.',
            'action' => (new AssistantActionResource($action))->resolve(),
        ]);
    }
}
