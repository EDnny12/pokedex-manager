<?php

namespace App\Http\Controllers;

use App\Actions\CreateAssistantAction;
use App\Actions\ExecuteAssistantAction;
use App\Enums\AssistantActionType;
use App\Exceptions\AssistantUserException;
use App\Http\Requests\CompareAssistantPokemonRequest;
use App\Http\Requests\FindAssistantPokemonRequest;
use App\Http\Requests\GetAssistantCollectionRequest;
use App\Http\Requests\GetAssistantPokemonMovesRequest;
use App\Http\Requests\SearchAssistantCatalogRequest;
use App\Http\Requests\StoreAssistantActionRequest;
use App\Http\Resources\AssistantActionResource;
use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\User;
use App\Services\Assistant\AssistantToolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalAssistantController extends Controller
{
    public function collection(GetAssistantCollectionRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->collection($this->user($request), $request->validated()));
    }

    public function collectionSummary(Request $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->summary($this->user($request)));
    }

    public function ownedPokemon(FindAssistantPokemonRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->ownedPokemon($this->user($request), $request->validated('pokemon')));
    }

    public function catalog(SearchAssistantCatalogRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->catalog($this->user($request), $request->validated()));
    }

    public function pokemon(FindAssistantPokemonRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->pokemon($request->validated('pokemon'), $this->user($request)));
    }

    public function compare(CompareAssistantPokemonRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->compare($request->validated('pokemon')));
    }

    public function forms(FindAssistantPokemonRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->forms($request->validated('pokemon')));
    }

    public function evolutionChain(FindAssistantPokemonRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->evolutionChain($request->validated('pokemon')));
    }

    public function typeMatchups(FindAssistantPokemonRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->typeMatchups($request->validated('pokemon')));
    }

    public function moves(GetAssistantPokemonMovesRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->moves(
            $request->validated('pokemon'),
            $request->string('learn_method')->toString(),
            $request->string('version_group')->toString(),
            $request->integer('limit', 20),
        ));
    }

    public function move(FindAssistantPokemonRequest $request, AssistantToolService $tools): JsonResponse
    {
        return response()->json($tools->move($request->validated('pokemon')));
    }

    public function storeAction(
        StoreAssistantActionRequest $request,
        CreateAssistantAction $createAction,
    ): AssistantActionResource|JsonResponse {
        try {
            $action = $createAction->handle(
                $this->user($request),
                $this->conversation($request),
                AssistantActionType::from($request->validated('type')),
                $request->validated('pokemon'),
                $request->validated('changes', []),
            );
        } catch (AssistantUserException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return new AssistantActionResource($action);
    }

    public function executeAction(
        Request $request,
        AssistantAction $assistantAction,
        ExecuteAssistantAction $execute,
    ): JsonResponse {
        if ($assistantAction->conversation_id !== $this->conversation($request)->getKey()) {
            abort(403);
        }

        return response()->json($execute->handle($this->user($request), $assistantAction));
    }

    private function user(Request $request): User
    {
        return $request->attributes->get('assistant_user');
    }

    private function conversation(Request $request): AssistantConversation
    {
        return $request->attributes->get('assistant_conversation');
    }
}
