<?php

namespace App\Models;

use App\Enums\AssistantMessageRole;
use Database\Factories\AssistantMessageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AssistantMessage extends Model
{
    /** @use HasFactory<AssistantMessageFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['role', 'content', 'metadata', 'client_message_id', 'reply_to_message_id'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssistantMessageAttachment::class, 'assistant_message_id');
    }

    public function assistantReply(): HasOne
    {
        return $this->hasOne(self::class, 'reply_to_message_id')
            ->where('role', AssistantMessageRole::Assistant);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => AssistantMessageRole::class,
            'metadata' => 'array',
        ];
    }
}
