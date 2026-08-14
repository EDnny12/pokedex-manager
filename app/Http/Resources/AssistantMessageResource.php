<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantMessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'role' => $this->resource->role->value,
            'content' => $this->resource->content,
            'metadata' => $this->resource->metadata ?? [],
            'attachments' => AssistantMessageAttachmentResource::collection(
                $this->resource->relationLoaded('attachments')
                    ? $this->resource->attachments
                    : collect(),
            )->resolve(),
            'created_at' => $this->resource->created_at?->toISOString(),
        ];
    }
}
