<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationChannelType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationChannelResource;
use App\Models\NotificationChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NotificationChannelController extends Controller
{
    /**
     * List all notification channels configured by the user.
     *
     * GET /notification-channels
     */
    public function index(Request $request): JsonResponse
    {
        $channels = NotificationChannel::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse(
            'Notification channels retrieved.',
            NotificationChannelResource::collection($channels),
        );
    }

    /**
     * Add a new notification channel.
     *
     * POST /notification-channels
     *
     * Body for Slack/Discord:
     *   { "channel": "slack", "label": "Team Alerts", "config": { "url": "https://hooks.slack.com/..." } }
     *
     * Body for Webhook:
     *   { "channel": "webhook", "label": "My Server", "config": { "url": "https://...", "secret": "..." } }
     *
     * Body for SMS:
     *   { "channel": "sms", "label": "My Phone", "config": { "phone": "+14155552671" } }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:' . implode(',', array_column(NotificationChannelType::userConfigurable(), 'value'))],
            'label'   => ['required', 'string', 'max:100'],
            'config'  => ['required', 'array'],
        ]);

        $channelType = NotificationChannelType::from($validated['channel']);

        $this->validateChannelConfig($channelType, $validated['config']);

        $channel = new NotificationChannel;
        $channel->user_id = $request->user()->id;
        $channel->channel = $channelType->value;
        $channel->label = $validated['label'];
        $channel->setConfigFromArray($validated['config']);
        $channel->is_active = true;
        $channel->save();

        return $this->successResponse(
            'Notification channel added.',
            new NotificationChannelResource($channel),
            201,
        );
    }

    /**
     * Update a notification channel.
     *
     * PUT /notification-channels/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $channel = NotificationChannel::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'label'     => ['sometimes', 'string', 'max:100'],
            'config'    => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['label'])) {
            $channel->label = $validated['label'];
        }

        if (isset($validated['config'])) {
            $channelType = $channel->channel instanceof NotificationChannelType
                ? $channel->channel
                : NotificationChannelType::from($channel->channel);

            $this->validateChannelConfig($channelType, $validated['config']);
            $channel->setConfigFromArray($validated['config']);
        }

        if (isset($validated['is_active'])) {
            $channel->is_active = $validated['is_active'];
        }

        $channel->save();

        return $this->successResponse('Notification channel updated.', new NotificationChannelResource($channel));
    }

    /**
     * Delete a notification channel.
     *
     * DELETE /notification-channels/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $channel = NotificationChannel::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $channel->delete();

        return $this->successResponse('Notification channel removed.');
    }

    /**
     * Send a test notification to verify the channel works.
     *
     * POST /notification-channels/{id}/test
     */
    public function test(Request $request, string $id): JsonResponse
    {
        $channel = NotificationChannel::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $config = $channel->getDecryptedConfig();
        $channelType = $channel->channel instanceof NotificationChannelType
            ? $channel->channel
            : NotificationChannelType::from($channel->channel);

        try {
            $this->sendTestMessage($channelType, $config, $request->user()->name ?? 'User');

            return $this->successResponse('Test notification sent successfully.');
        } catch (\Throwable $e) {
            return $this->errorResponse('Test notification failed: ' . $e->getMessage(), 422);
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function validateChannelConfig(NotificationChannelType $type, array $config): void
    {
        $rules = match ($type) {
            NotificationChannelType::Slack,
            NotificationChannelType::Discord => ['url' => ['required', 'url']],
            NotificationChannelType::Webhook  => ['url' => ['required', 'url'], 'secret' => ['nullable', 'string']],
            NotificationChannelType::Sms      => ['phone' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/']],
            default => [],
        };

        if (empty($rules)) {
            return;
        }

        validator($config, $rules)->validate();
    }

    private function sendTestMessage(NotificationChannelType $type, array $config, string $userName): void
    {
        $timeout = config('notifications.webhook_timeout', 10);

        match ($type) {
            NotificationChannelType::Slack => Http::timeout($timeout)->post($config['url'], [
                'text' => "✅ LinkFlow test notification — channel connected successfully! (User: {$userName})",
            ]),
            NotificationChannelType::Discord => Http::timeout($timeout)->post($config['url'], [
                'embeds' => [[
                    'title'       => '✅ Test Notification',
                    'color'       => 0x57F287,
                    'description' => "LinkFlow notification channel connected successfully!\nUser: {$userName}",
                    'timestamp'   => now()->toIso8601String(),
                ]],
            ]),
            NotificationChannelType::Webhook => Http::timeout($timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($config['url'], [
                    'event'   => 'test',
                    'message' => 'LinkFlow test notification',
                    'user'    => $userName,
                    'time'    => now()->toIso8601String(),
                ]),
            NotificationChannelType::Sms => null, // Twilio test not sent to save credits
            default => null,
        };
    }
}
