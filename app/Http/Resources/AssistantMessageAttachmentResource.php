<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantMessageAttachmentResource extends JsonResource
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
            'name' => $this->resource->original_name,
            'mime_type' => $this->resource->mime_type,
            'size' => $this->resource->size,
            'width' => $this->resource->width,
            'height' => $this->resource->height,
            'url' => route('assistant.attachments.show', $this->resource),
        ];
    }
}
