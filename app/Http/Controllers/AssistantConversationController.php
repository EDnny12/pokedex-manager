<?php

namespace App\Http\Controllers;

use App\Actions\PaginateAssistantMessages;
use App\Http\Requests\PaginateAssistantMessagesRequest;
use App\Http\Requests\StoreAssistantConversationRequest;
use App\Http\Resources\AssistantActionResource;
use App\Http\Resources\AssistantConversationResource;
use App\Http\Resources\AssistantMessageResource;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Services\Assistant\AssistantAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Gate;

class AssistantConversationController extends Controller
{
    public function index(Request $request, PaginateAssistantMessages $paginateMessages): JsonResponse
    {
        Gate::authorize('viewAny', AssistantConversation::class);

        $conversations = $request->user()->assistantConversations()
            ->withLatestMessagePreview()
            ->orderByRaw('last_message_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $requestedConversation = $request->string('conversation')->toString();
        $activeConversation = $requestedConversation !== ''
            ? $request->user()->assistantConversations()->find($requestedConversation)
            : $conversations->first();
        $messagePage = $activeConversation === null
            ? null
            : $paginateMessages->handle($activeConversation);

        return response()->json([
            'conversations' => AssistantConversationResource::collection($conversations)->resolve(),
            'active_conversation' => $activeConversation === null
                ? null
                : (new AssistantConversationResource($activeConversation))->resolve(),
            'messages' => $messagePage === null
                ? $this->emptyMessagePage()
                : $this->resolveMessagePage($messagePage),
            'actions' => $activeConversation === null
                ? []
                : AssistantActionResource::collection(
                    $activeConversation->actions()
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->limit(50)
                        ->get(),
                )->resolve(),
        ]);
    }

    public function messages(
        PaginateAssistantMessagesRequest $request,
        AssistantConversation $assistantConversation,
        PaginateAssistantMessages $paginateMessages,
    ): JsonResponse {
        $messagePage = $paginateMessages->handle(
            $assistantConversation,
            $request->paginationCursor(),
        );

        return response()->json(['messages' => $this->resolveMessagePage($messagePage)]);
    }

    public function show(
        Request $request,
        AssistantConversation $assistantConversation,
        PaginateAssistantMessages $paginateMessages,
    ): JsonResponse {
        Gate::authorize('view', $assistantConversation);

        $messagePage = $paginateMessages->handle($assistantConversation);

        return response()->json([
            'active_conversation' => (new AssistantConversationResource($assistantConversation))->resolve(),
            'messages' => $this->resolveMessagePage($messagePage),
            'actions' => AssistantActionResource::collection(
                $assistantConversation->actions()
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->limit(50)
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

    /**
     * @param  CursorPaginator<int, AssistantMessage>  $messagePage
     * @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool}
     */
    private function resolveMessagePage(CursorPaginator $messagePage): array
    {
        return [
            'data' => AssistantMessageResource::collection(
                $messagePage->getCollection()->reverse()->values(),
            )->resolve(),
            'next_cursor' => $messagePage->nextCursor()?->encode(),
            'has_more' => $messagePage->hasMorePages(),
        ];
    }

    /** @return array{data: array<never, never>, next_cursor: null, has_more: false} */
    private function emptyMessagePage(): array
    {
        return [
            'data' => [],
            'next_cursor' => null,
            'has_more' => false,
        ];
    }
}
