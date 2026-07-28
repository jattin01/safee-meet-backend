<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /notifications — paginated in-app notification list, latest first.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'in:10,20,50'],
        ]);

        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($validated['per_page'] ?? 20);

        $notifications->getCollection()->transform(fn (Notification $n) => [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data,
            'is_read' => $n->is_read,
            'created_at' => $n->created_at,
        ]);

        return response()->json($notifications);
    }

    /**
     * GET /notifications/unread-count — badge count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => Notification::where('user_id', $request->user()->id)->unread()->count(),
        ]);
    }

    /**
     * POST /notifications/{notification}/read — mark one as read.
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        $this->authorizeOwner($request, $notification);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * POST /notifications/read-all — mark all as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * DELETE /notifications/{notification}
     */
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        $this->authorizeOwner($request, $notification);
        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }

    /**
     * GET /notification-preferences — current toggles (created on first read).
     */
    public function preferences(Request $request): JsonResponse
    {
        $pref = $request->user()->notificationPreferences()->firstOrCreate([]);

        return response()->json($pref);
    }

    /**
     * PUT /notification-preferences — update toggles.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'meeting_alerts' => ['sometimes', 'boolean'],
            'sos_alerts' => ['sometimes', 'boolean'],
            'chat_notifications' => ['sometimes', 'boolean'],
            'marketing_emails' => ['sometimes', 'boolean'],
        ]);

        $pref = $request->user()->notificationPreferences()->firstOrCreate([]);
        $pref->update($validated);

        return response()->json($pref);
    }

    private function authorizeOwner(Request $request, Notification $notification): void
    {
        // User::id is cast to string, notification.user_id is an int — compare
        // as strings to avoid a strict-type mismatch.
        abort_unless((string) $notification->user_id === (string) $request->user()->id, 403, 'Not your notification.');
    }
}
