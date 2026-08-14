<?php

namespace App\Services\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\AssistantMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AssistantAttachmentService
{
    /**
     * @param  list<UploadedFile>  $images
     * @return Collection<int, AssistantMessageAttachment>
     */
    public function store(AssistantMessage $message, array $images): Collection
    {
        $disk = (string) config('services.assistant.attachment_disk', 'local');
        $storedPaths = [];

        try {
            return collect($images)->map(function (UploadedFile $image) use ($message, $disk, &$storedPaths): AssistantMessageAttachment {
                $mimeType = $this->normalizedMimeType($image);
                $extension = match ($mimeType) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => throw new RuntimeException('El formato de la imagen no es compatible.'),
                };
                $directory = "assistant/{$message->conversation_id}/{$message->getKey()}";
                $path = Storage::disk($disk)->putFileAs($directory, $image, Str::uuid().'.'.$extension);

                if (! is_string($path)) {
                    throw new RuntimeException('No pudimos guardar la imagen adjunta.');
                }

                $storedPaths[] = $path;
                $dimensions = getimagesize($image->getRealPath());

                return $message->attachments()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'original_name' => $this->safeOriginalName($image),
                    'mime_type' => $mimeType,
                    'size' => (int) $image->getSize(),
                    'width' => is_array($dimensions) ? $dimensions[0] : null,
                    'height' => is_array($dimensions) ? $dimensions[1] : null,
                ]);
            })->values();
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedPaths);
            throw $exception;
        }
    }

    /** @return array{mimeType: string, data: string}|null */
    public function toAgentPayload(AssistantMessageAttachment $attachment): ?array
    {
        $contents = Storage::disk($attachment->disk)->get($attachment->path);

        if (! is_string($contents)) {
            return null;
        }

        return [
            'mimeType' => $attachment->mime_type,
            'data' => base64_encode($contents),
        ];
    }

    public function deleteConversationFiles(AssistantConversation $conversation): void
    {
        $attachments = AssistantMessageAttachment::query()
            ->whereHas('message', fn ($query) => $query->where('conversation_id', $conversation->getKey()))
            ->get(['disk', 'path']);

        $this->deleteFiles($attachments);
    }

    public function deleteMessageFiles(AssistantMessage $message): void
    {
        $this->deleteFiles($message->attachments()->get(['disk', 'path']));
    }

    /** @param Collection<int, AssistantMessageAttachment> $attachments */
    private function deleteFiles(Collection $attachments): void
    {
        $attachments->groupBy('disk')->each(function (Collection $diskAttachments, string $disk): void {
            Storage::disk($disk)->delete($diskAttachments->pluck('path')->all());
        });
    }

    private function normalizedMimeType(UploadedFile $image): string
    {
        return match ($image->getMimeType()) {
            'image/jpg', 'image/pjpeg' => 'image/jpeg',
            'image/x-png' => 'image/png',
            default => (string) $image->getMimeType(),
        };
    }

    private function safeOriginalName(UploadedFile $image): string
    {
        $name = basename(str_replace('\\', '/', $image->getClientOriginalName()));
        $name = preg_replace('/[^\pL\pN._ -]/u', '_', $name) ?: 'imagen';

        return Str::limit($name, 180, '');
    }
}
