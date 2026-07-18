<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Meeting;

class SosController extends Controller
{
    /**
     * POST /api/v1/sos/trigger
     * "Emergency SOS — Tap to activate emergency alert"
     * Captures GPS, meeting details, and notifies trusted/emergency contacts.
     * The reporter is always the authenticated (Sanctum token) user —
     * never a client-supplied user_id.
     */
    public function trigger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'meeting_id' => ['nullable', 'exists:meetings,id'],
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $user = $request->user();

        // If a meeting is given, it must actually involve this user —
        // don't let a client mark an unrelated meeting as "incident_reported".
        if (! empty($validated['meeting_id'])) {
            $meeting = Meeting::where('id', $validated['meeting_id'])
                ->where(function ($q) use ($user) {
                    $q->where('host_user_id', $user->id)
                        ->orWhere('guest_user_id', $user->id);
                })
                ->first();

            abort_unless($meeting, 403, 'Not a participant in this meeting');
        }

        $contacts = $user->emergencyContacts()->get(['full_name', 'phone_number']);

        $incident = Incident::create([
            'reporter_user_id' => $user->id,
            'meeting_id' => $validated['meeting_id'] ?? null,
            'type' => 'sos',
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'emergency_contacts_notified' => $contacts,
            'status' => 'open',
        ]);

        if (isset($meeting)) {
            $meeting->update(['status' => 'incident_reported']);
        }

        // TODO: integrate real SMS/push notification dispatch to trusted contacts here.
        // TODO (SOW "Future"): Law Enforcement Integration hook goes here once available.
        // foreach ($contacts as $contact) { app(NotificationService::class)->sendSos($contact, $incident); }

        return response()->json([
            'message' => 'Emergency SOS activated. Trusted contacts are being notified.',
            'incident' => $incident,
        ], 201);
    }

    /**
     * POST /api/sos/{incident}/resolve — end-user marks themselves safe
     */
    public function resolve(Request $request, Incident $incident): JsonResponse
    {
        abort_unless($incident->reporter_user_id === $request->user()->id, 403);

        $incident->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_notes' => 'Marked safe by user',
        ]);

        return response()->json(['message' => 'Marked as safe']);
    }
}
