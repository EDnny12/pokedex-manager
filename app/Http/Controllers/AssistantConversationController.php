<?php

namespace App\Http\Controllers;

use App\Enums\AssistantActionStatus;
use App\Http\Requests\StoreAssistantConversationRequest;
use App\Http\Resources\AssistantActionResource;
use App\Http\Resources\AssistantConversationResource;
use App\Http\Resources\AssistantMessageResource;
use App\Models\AssistantConversation;
use App\Services\Assistant\AssistantAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssistantConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AssistantConversation::class);

        $conversations = $request->user()->assistantConversations()
            ->with('latestMessage:id,conversation_id,content')
            ->latest('last_message_at')
            ->latest()
            ->limit(30)
            ->get();

        $requestedConversation = $request->string('conversation')->toString();
        $activeConversation = $requestedConversation !== ''
            ? $request->user()->assistantConversations()->find($requestedConversation)
            : $conversations->first();

        return response()->json([
            'conversations' => AssistantConversationResource::collection($conversations)->resolve(),
            'active_conversation' => $activeConversation === null
                ? null
                : (new AssistantConversationResource($activeConversation))->resolve(),
            'messages' => $activeConversation === null
                ? []
                : AssistantMessageResource::collection(
                    $activeConversation->messages()->with('attachments')->oldest()->limit(100)->get(),
                )->resolve(),
            'actions' => $activeConversation === null
                ? []
                : AssistantActionResource::collection(
                    $activeConversation->actions()
                        ->whereIn('status', [AssistantActionStatus::Pending, AssistantActionStatus::Confirmed])
                        ->latest()
                        ->get(),
                )->resolve(),
        ]);
    }

    public function store(StoreAssistantConversationRequest $request): AssistantConversationResource
    {
        Gate::authorize('create', AssistantConversation::class);
        $conversation = $request->user()->assistantConversations()->create([
            'title' => $request->validated('title') ?: 'Nueva conversación',
        ]);

        return new AssistantConversationResource($conversation);
    }

    public function destroy(
        AssistantConversation $assistantConversation,
        AssistantAttachmentService $attachments,
    ): JsonResponse {
        Gate::authorize('delete', $assistantConversation);
        $attachments->deleteConversationFiles($assistantConversation);
        $assistantConversation->delete();

        return response()->json(['message' => 'Conversación eliminada.']);
    }
}
