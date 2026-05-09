<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * Get all notification preferences for the authenticated user.
     * Returns defaults for any types that have no stored preference.
     *
     * GET /notification-preferences
     */
    public function index(Request $request): JsonResponse
    {
        $preferences = $this->notificationService->getPreferences($request->user());

        $grouped = [];
        foreach (NotificationType::userGroups() as $groupName => $types) {
            $grouped[$groupName] = NotificationPreferenceResource::collection(
                collect($types)->map(fn ($type) => $preferences[$type->value] ?? null)->filter()->values()
            );
        }

        return $this->successResponse('Notification preferences retrieved.', [
            'preferences'        => NotificationPreferenceResource::collection(array_values($preferences)),
            'grouped'            => $grouped,
            'available_channels' => collect(NotificationChannelType::cases())->map(fn ($ch) => [
                'value'                  => $ch->value,
                'label'                  => $ch->label(),
                'requires_stored_config' => $ch->requiresStoredConfig(),
            ]),
        ]);
    }

    /**
     * Update notification preferences for the authenticated user.
     *
     * PUT /notification-preferences
     *
     * Body:
     * {
     *   "preferences": {
     *     "execution.failed": { "enabled": true, "channels": ["mail", "slack"] },
     *     "quota.warning":    { "enabled": false, "channels": [] }
     *   }
     * }
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences'                  => ['required', 'array'],
            'preferences.*'                => ['array'],
            'preferences.*.enabled'        => ['boolean'],
            'preferences.*.channels'       => ['array'],
            'preferences.*.channels.*'     => ['string', 'in:' . implode(',', array_column(NotificationChannelType::cases(), 'value'))],
        ]);

        $this->notificationService->updatePreferences($request->user(), $validated['preferences']);

        $updated = $this->notificationService->getPreferences($request->user());

        return $this->successResponse(
            'Notification preferences updated.',
            NotificationPreferenceResource::collection(array_values($updated)),
        );
    }
}
