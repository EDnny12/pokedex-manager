<?php

namespace App\Actions;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

class PaginateAssistantMessages
{
    /**
     * @return CursorPaginator<int, AssistantMessage>
     */
    public function handle(AssistantConversation $conversation, ?Cursor $cursor = null): CursorPaginator
    {
        $perPage = max(20, min(100, (int) config('services.assistant.message_page_size', 50)));

        return $conversation->messages()
            ->select(['id', 'conversation_id', 'role', 'content', 'metadata', 'client_message_id', 'created_at'])
            ->with(['attachments' => fn ($query) => $query->select([
                'id',
                'assistant_message_id',
                'original_name',
                'mime_type',
                'size',
                'width',
                'height',
            ])])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage, cursor: $cursor);
    }
}
