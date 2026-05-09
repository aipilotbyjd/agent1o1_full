<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\NotificationChannelType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationChannelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $channelType = $this->channel instanceof NotificationChannelType
            ? $this->channel
            : NotificationChannelType::tryFrom($this->channel);

        $config = $this->getDecryptedConfig();

        // Mask sensitive values from the API response
        if (isset($config['url'])) {
            $config['url'] = $this->maskUrl($config['url']);
        }

        if (isset($config['secret'])) {
            $config['secret'] = '••••••••';
        }

        if (isset($config['phone'])) {
            $config['phone'] = $this->maskPhone($config['phone']);
        }

        return [
            'id'         => $this->id,
            'channel'    => $channelType?->value ?? $this->channel,
            'label_type' => $channelType?->label() ?? $this->channel,
            'label'      => $this->label,
            'config'     => $config,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function maskUrl(string $url): string
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        return ($parsed['scheme'] ?? 'https') . '://' . $host . '/••••••••';
    }

    private function maskPhone(string $phone): string
    {
        return substr($phone, 0, 3) . str_repeat('•', max(0, strlen($phone) - 6)) . substr($phone, -3);
    }
}
