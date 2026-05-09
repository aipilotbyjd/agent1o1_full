<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\NotificationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->type instanceof NotificationType ? $this->type : NotificationType::tryFrom($this->type);

        return [
            'id'       => $this->id,
            'type'     => $type?->value ?? $this->type,
            'label'    => $type?->label() ?? $this->type,
            'channels' => $this->channels ?? [],
            'enabled'  => $this->enabled,
        ];
    }
}
