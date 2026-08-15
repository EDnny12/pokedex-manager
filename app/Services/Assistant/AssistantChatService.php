<?php

namespace App\Services\Assistant;

use App\Contracts\AssistantAgent;
use App\Enums\AssistantMessageRole;
use App\Exceptions\AssistantUserException;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class AssistantChatService
{
    public function __construct(
        private AssistantAgent $assistantAgent,
        private AssistantAttachmentService $attachments,
    ) {}

    /**
     * @param  list<UploadedFile>  $images
     * @return array{user_message: AssistantMessage, assistant_message: AssistantMessage}
     */
    public function send(
        User $user,
        AssistantConversation $conversation,
        string $content,
        string $clientMessageId,
        array $images = [],
    ): array {
        $lockSeconds = max(30, (int) config('services.assistant.request_lock_seconds', 120));
        $waitSeconds = max(1, (int) config('services.assistant.request_lock_wait_seconds', 15));

        return Cache::lock(
            "assistant:message:{$conversation->getKey()}:{$clientMessageId}",
            $lockSeconds,
        )->block(
            $waitSeconds,
            fn (): array => $this->sendOnce(
                $user,
                $conversation,
                $content,
                $clientMessageId,
                $images,
                $lockSeconds,
            ),
        );
    }

    /**
     * @param  list<UploadedFile>  $images
     * @return array{user_message: AssistantMessage, assistant_message: AssistantMessage}
     */
    private function sendOnce(
        User $user,
        AssistantConversation $conversation,
        string $content,
        string $clientMessageId,
        array $images,
        int $processingWindowSeconds,
    ): array {
        $existingUserMessage = $conversation->messages()
            ->where('client_message_id', $clientMessageId)
            ->first();

        if ($existingUserMessage instanceof AssistantMessage) {
            $assistantMessage = $existingUserMessage->assistantReply()->with('attachments')->first();

            if ($assistantMessage instanceof AssistantMessage) {
                return [
                    'user_message' => $existingUserMessage->load('attachments'),
                    'assistant_message' => $assistantMessage->load('attachments'),
                ];
            }

            if ($existingUserMessage->created_at->gt(now()->subSeconds($processingWindowSeconds))) {
                throw new AssistantUserException(
                    'Este mensaje todavía se está procesando. Inténtalo nuevamente en unos segundos.',
                );
            }
        }

        $requestId = data_get($existingUserMessage?->metadata, 'request_id');
        $requestId = is_string($requestId) && $requestId !== ''
            ? $requestId
            : Str::uuid()->toString();
        $historyMessages = $conversation->messages()
            ->when(
                $existingUserMessage instanceof AssistantMessage,
                function ($query) use ($existingUserMessage): void {
                    $query->where(function ($query) use ($existingUserMessage): void {
                        $query
                            ->where('created_at', '<', $existingUserMessage->created_at)
                            ->orWhere(function ($query) use ($existingUserMessage): void {
                                $query
                                    ->where('created_at', $existingUserMessage->created_at)
                                    ->where('id', '<', $existingUserMessage->getKey());
                            });
                    });
                },
            )
            ->select(['id', 'conversation_id', 'role', 'content', 'created_at'])
            ->with('attachments')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit((int) config('services.assistant.history_limit', 16))
            ->get()
            ->reverse()
            ->values();
        $normalizedContent = Str::squish($content);
        $createdUserMessage = false;

        if ($normalizedContent === '') {
            $normalizedContent = count($images) === 1
                ? 'Analiza esta imagen.'
                : 'Analiza estas imágenes.';
        }

        if ($existingUserMessage instanceof AssistantMessage) {
            $userMessage = $existingUserMessage->load('attachments');
        } else {
            $userMessage = $conversation->messages()->createOrFirst([
                'client_message_id' => $clientMessageId,
            ], [
                'role' => AssistantMessageRole::User,
                'content' => $normalizedContent,
                'metadata' => ['request_id' => $requestId],
            ]);
            $createdUserMessage = true;

            if (! $userMessage->wasRecentlyCreated) {
                $assistantMessage = $userMessage->assistantReply()->with('attachments')->first();

                if ($assistantMessage instanceof AssistantMessage) {
                    return [
                        'user_message' => $userMessage->load('attachments'),
                        'assistant_message' => $assistantMessage->load('attachments'),
                    ];
                }

                throw new AssistantUserException(
                    'Este mensaje todavía se está procesando. Inténtalo nuevamente en unos segundos.',
                );
            }

            try {
                $userMessage->setRelation('attachments', $this->attachments->store($userMessage, $images));
            } catch (Throwable $exception) {
                $userMessage->delete();
                throw $exception;
            }

            if ($conversation->messages()->count() === 1) {
                $conversation->update(['title' => Str::limit($normalizedContent, 55, '…')]);
            }
        }

        try {
            $currentAttachments = $userMessage->attachments;
            $history = $this->historyPayload($historyMessages, $currentAttachments);
            $imagePayload = $currentAttachments
                ->map(fn ($attachment): ?array => $this->attachments->toAgentPayload($attachment))
                ->filter()
                ->values()
                ->all();
            $response = $this->assistantAgent->respond(
                $user,
                $conversation,
                $history,
                $userMessage->content,
                $imagePayload,
                $requestId,
            );
        } catch (Throwable $exception) {
            if ($createdUserMessage) {
                $this->attachments->deleteMessageFiles($userMessage);
                $userMessage->delete();

                if ($conversation->messages()->doesntExist()) {
                    $conversation->update([
                        'title' => 'Nueva conversación',
                        'last_message_at' => null,
                    ]);
                }
            }

            throw $exception;
        }

        $assistantMessage = $conversation->messages()->createOrFirst(
            ['reply_to_message_id' => $userMessage->getKey()],
            [
                'role' => AssistantMessageRole::Assistant,
                'content' => $response['content'],
                'metadata' => $response['metadata'],
            ],
        );
        $assistantMessage->setRelation('attachments', $assistantMessage->relationLoaded('attachments')
            ? $assistantMessage->attachments
            : collect());

        $conversation->update(['last_message_at' => now()]);

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    /**
     * @param  Collection<int, AssistantMessage>  $messages
     * @param  Collection<int, mixed>  $currentAttachments
     * @return list<array{role: string, content: string, attachments: list<array{mimeType: string, data: string}>}>
     */
    private function historyPayload(Collection $messages, Collection $currentAttachments): array
    {
        $remainingBytes = max(
            0,
            (int) config('services.assistant.image_context_bytes', 12_582_912)
                - $currentAttachments->sum('size'),
        );
        $remainingImages = (int) config('services.assistant.image_history_limit', 2);
        $selectedAttachmentIds = [];

        foreach ($messages->reverse() as $message) {
            foreach ($message->attachments->reverse() as $attachment) {
                if ($remainingImages === 0) {
                    break 2;
                }

                if ($attachment->size <= $remainingBytes) {
                    $selectedAttachmentIds[] = $attachment->getKey();
                    $remainingBytes -= $attachment->size;
                    $remainingImages--;
                }
            }
        }

        return $messages->map(fn (AssistantMessage $message): array => [
            'role' => $message->role->value,
            'content' => $message->content,
            'attachments' => $message->attachments
                ->whereIn('id', $selectedAttachmentIds)
                ->map(fn ($attachment): ?array => $this->attachments->toAgentPayload($attachment))
                ->filter()
                ->values()
                ->all(),
        ])->values()->all();
    }
}
