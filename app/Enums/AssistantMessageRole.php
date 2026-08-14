<?php

namespace App\Enums;

enum AssistantMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
