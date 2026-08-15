<?php

namespace App\Http\Controllers;

use App\Exceptions\AssistantUserException;
use App\Http\Requests\StoreAssistantMessageRequest;
use App\Http\Resources\AssistantConversationResource;
use App\Http\Resources\AssistantMessageResource;
use App\Models\AssistantConversation;
use App\Services\Assistant\AssistantChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Throwable;

class AssistantMessageController extends Controller
{
    public function store(
        StoreAssistantMessageRequest $request,
        AssistantConversation $assistantConversation,
        AssistantChatService $chat,
    ): JsonResponse {
        /** @var list<UploadedFile> $images */
        $images = $request->file('images', []);

        try {
            $result = $chat->send(
                $request->user(),
                $assistantConversation,
                $request->string('message')->toString(),
                $request->string('client_message_id')->toString(),
                $images,
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => $exception instanceof AssistantUserException
                    ? $exception->getMessage()
                    : 'No pudimos obtener una respuesta. Inténtalo de nuevo.',
            ], 503);
        }

        return response()->json([
            'conversation' => (new AssistantConversationResource($assistantConversation->fresh()))->resolve(),
            'user_message' => (new AssistantMessageResource($result['user_message']))->resolve(),
            'assistant_message' => (new AssistantMessageResource($result['assistant_message']))->resolve(),
        ]);
    }
}
