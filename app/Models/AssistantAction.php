<?php

namespace App\Models;

use App\Enums\AssistantActionStatus;
use App\Enums\AssistantActionType;
use Database\Factories\AssistantActionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantAction extends Model
{
    /** @use HasFactory<AssistantActionFactory> */
    use HasFactory;

    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'payload',
        'status',
        'expires_at',
        'executed_at',
        'failure_message',
    ];

    /** @var array<string, mixed> */
    protected $attributes = ['status' => AssistantActionStatus::Pending->value];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AssistantConversation::class, 'conversation_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AssistantActionType::class,
            'status' => AssistantActionStatus::class,
            'payload' => 'array',
            'expires_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }
}
