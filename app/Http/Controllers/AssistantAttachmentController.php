<?php

namespace App\Http\Controllers;

use App\Models\AssistantMessageAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssistantAttachmentController extends Controller
{
    public function show(AssistantMessageAttachment $assistantMessageAttachment): StreamedResponse
    {
        Gate::authorize('view', $assistantMessageAttachment);

        abort_unless(
            Storage::disk($assistantMessageAttachment->disk)->exists($assistantMessageAttachment->path),
            404,
        );

        return Storage::disk($assistantMessageAttachment->disk)->response(
            $assistantMessageAttachment->path,
            $assistantMessageAttachment->original_name,
            [
                'Cache-Control' => 'private, max-age=3600',
                'Content-Type' => $assistantMessageAttachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }
}
