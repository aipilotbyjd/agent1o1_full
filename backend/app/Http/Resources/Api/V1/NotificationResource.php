<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = is_string($this->data) ? json_decode($this->data, true) : $this->data;

        return [
            'id'         => $this->id,
            'type'       => $data['type'] ?? null,
            'title'      => $data['title'] ?? null,
            'body'       => $data['body'] ?? null,
            'icon'       => $data['icon'] ?? 'info',
            'data'       => $data,
            'read'       => $this->read_at !== null,
            'read_at'    => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
