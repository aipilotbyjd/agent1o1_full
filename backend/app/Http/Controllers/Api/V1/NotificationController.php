<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * List paginated notifications for the authenticated user.
     *
     * GET /notifications?unread=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->notifications()
            ->latest();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->paginate($request->integer('per_page', 20));

        return $this->successResponse(
            'Notifications retrieved successfully.',
            NotificationResource::collection($notifications)->response()->getData(true),
        );
    }

    /**
     * Get the count of unread notifications.
     *
     * GET /notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->count();

        return $this->successResponse('Unread count retrieved.', ['count' => $count]);
    }

    /**
     * Mark a specific notification as read.
     *
     * POST /notifications/{id}/read
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return $this->successResponse('Notification marked as read.', new NotificationResource($notification));
    }

    /**
     * Mark all notifications as read.
     *
     * POST /notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->successResponse('All notifications marked as read.');
    }

    /**
     * Delete a specific notification.
     *
     * DELETE /notifications/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->delete();

        return $this->successResponse('Notification deleted.');
    }

    /**
     * Delete all notifications for the user.
     *
     * DELETE /notifications
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->notifications()->delete();

        return $this->successResponse('All notifications deleted.');
    }
}
