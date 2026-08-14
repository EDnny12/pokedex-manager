<?php

namespace App\Contracts;

use App\Models\AssistantAction;
use App\Models\AssistantConversation;
use App\Models\User;

interface AssistantAgent
{
    /**
     * @param  array<int, array{role: string, content: string, attachments: list<array{mimeType: string, data: string}>}>  $history
     * @param  list<array{mimeType: string, data: string}>  $attachments
     * @return array{content: string, metadata: array<string, mixed>}
     */
    public function respond(
        User $user,
        AssistantConversation $conversation,
        array $history,
        string $message,
        array $attachments,
        string $requestId,
    ): array;

    /** @return array<string, mixed> */
    public function execute(User $user, AssistantAction $action, string $requestId): array;
}
