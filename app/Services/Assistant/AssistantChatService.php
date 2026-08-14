<?php

namespace App\Services\Assistant;

use App\Contracts\AssistantAgent;
use App\Enums\AssistantMessageRole;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
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
        $existingUserMessage = $conversation->messages()
            ->where('client_message_id', $clientMessageId)
            ->first();

        if ($existingUserMessage instanceof AssistantMessage) {
            $assistantMessage = $conversation->messages()
                ->where('created_at', '>=', $existingUserMessage->created_at)
                ->where('role', AssistantMessageRole::Assistant)
                ->oldest()
                ->first();

            if ($assistantMessage instanceof AssistantMessage) {
                return [
                    'user_message' => $existingUserMessage->load('attachments'),
                    'assistant_message' => $assistantMessage->load('attachments'),
                ];
            }
        }

        $requestId = Str::uuid()->toString();
        $historyMessages = $conversation->messages()
            ->when(
                $existingUserMessage instanceof AssistantMessage,
                fn ($query) => $query->where('created_at', '<', $existingUserMessage->created_at),
            )
            ->select(['id', 'conversation_id', 'role', 'content', 'created_at'])
            ->with('attachments')
            ->latest()
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
            $userMessage = $conversation->messages()->create([
                'role' => AssistantMessageRole::User,
                'content' => $normalizedContent,
                'client_message_id' => $clientMessageId,
                'metadata' => ['request_id' => $requestId],
            ]);
            $createdUserMessage = true;

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

        $assistantMessage = $conversation->messages()->create([
            'role' => AssistantMessageRole::Assistant,
            'content' => $response['content'],
            'metadata' => $response['metadata'],
        ]);
        $assistantMessage->setRelation('attachments', collect());

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
