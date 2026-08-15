<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantConversationResource extends JsonResource
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
            'title' => $this->resource->title,
            'last_message_at' => $this->resource->last_message_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'preview' => $this->resource->getAttribute('preview'),
        ];
    }
}
