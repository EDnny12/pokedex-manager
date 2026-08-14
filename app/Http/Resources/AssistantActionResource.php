<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantActionResource extends JsonResource
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
            'type' => $this->resource->type->value,
            'payload' => $this->resource->payload,
            'status' => $this->resource->status->value,
            'expires_at' => $this->resource->expires_at?->toISOString(),
            'executed_at' => $this->resource->executed_at?->toISOString(),
        ];
    }
}
