<?php

namespace App\Policies;

use App\Models\AssistantMessageAttachment;
use App\Models\User;

class AssistantMessageAttachmentPolicy
{
    public function view(User $user, AssistantMessageAttachment $assistantMessageAttachment): bool
    {
        return $assistantMessageAttachment->message()
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $user->getKey()))
            ->exists();
    }
}
