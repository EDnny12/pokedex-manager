<?php

namespace App\Enums;

enum AssistantActionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Executed = 'executed';
    case Failed = 'failed';
    case Expired = 'expired';
}
