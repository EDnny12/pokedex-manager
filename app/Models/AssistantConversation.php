<?php

namespace App\Models;

use Database\Factories\AssistantConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantConversation extends Model
{
    /** @use HasFactory<AssistantConversationFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['title', 'last_message_at'];

    /** @var array<string, mixed> */
    protected $attributes = ['title' => 'Nueva conversación'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AssistantAction::class, 'conversation_id');
    }

    #[Scope]
    protected function withLatestMessagePreview(Builder $query): Builder
    {
        $conversationId = $query->getModel()->qualifyColumn('id');
        $message = new AssistantMessage;

        return $query->addSelect([
            'preview' => AssistantMessage::query()
                ->select($message->qualifyColumn('content'))
                ->whereColumn($message->qualifyColumn('conversation_id'), $conversationId)
                ->orderByDesc($message->qualifyColumn('created_at'))
                ->orderByDesc($message->qualifyColumn('id'))
                ->limit(1),
        ]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }
}
