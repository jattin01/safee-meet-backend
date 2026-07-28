<?php

namespace App\Services;

use App\Models\Notification as NotificationModel;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Throwable;

class PushNotificationService
{
    /**
     * Delivers a notification to a user:
     *   1. respects the category toggle in notification_preferences — if the
     *      user muted this category, nothing happens;
     *   2. persists an in-app notification row (so it shows in the list even
     *      if the device has no token / push is off);
     *   3. sends the FCM push only when push is enabled and a token exists.
     *
     * Never throws — a notification failure must never break the endpoint that
     * triggered it.
     *
     * @param  array<string, string>  $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data): void
    {
        $type = $data['type'] ?? 'general';
        // Fresh read (not the possibly-cached relation) so gating always
        // reflects the user's current preferences.
        $pref = $user->notificationPreferences()->first();

        // Category opted out entirely → no store, no push.
        if (! $this->categoryEnabled($pref, $type)) {
            return;
        }

        $this->persist($user, $type, $title, $body, $data);

        // Push is a separate switch: keep the in-app record but skip FCM.
        if ($pref && ! $pref->push_enabled) {
            return;
        }

        $this->sendFcm($user, $title, $body, $data);
    }

    private function persist(User $user, string $type, string $title, string $body, array $data): void
    {
        try {
            NotificationModel::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::warning('Notification failed to persist', [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendFcm(User $user, string $title, string $body, array $data): void
    {
        if (! $user->fcm_token) {
            Log::info('Push notification skipped — user has no fcm_token on file', [
                'user_id' => $user->id,
                'type' => $data['type'] ?? null,
            ]);
            return;
        }

        try {
            $message = CloudMessage::new()
                ->toToken($user->fcm_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            Firebase::messaging()->send($message);
            Log::info('Push notification sent', [
                'user_id' => $user->id,
                'type' => $data['type'] ?? null,
            ]);
        } catch (Throwable $e) {
            Log::warning('Push notification failed to send', [
                'user_id' => $user->id,
                'type' => $data['type'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Map a notification type to its preference category toggle. */
    private function categoryEnabled(?NotificationPreference $pref, string $type): bool
    {
        if (! $pref) {
            return true; // no preferences row → nothing muted
        }

        return match (true) {
            str_starts_with($type, 'meeting') => (bool) $pref->meeting_alerts,
            str_starts_with($type, 'sos') => (bool) $pref->sos_alerts,
            str_starts_with($type, 'chat') => (bool) $pref->chat_notifications,
            default => true,
        };
    }
}
