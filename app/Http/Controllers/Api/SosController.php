<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Meeting;


class SosController extends Controller
{
    /**
     * POST /api/sos/trigger
     * "Emergency SOS — Tap to activate emergency alert"
     * Captures GPS, meeting details, and notifies trusted/emergency contacts.
     */
    // public function trigger(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'meeting_id' => ['nullable', 'exists:meetings,id'],
    //         'latitude' => ['required', 'numeric'],
    //         'longitude' => ['required', 'numeric'],
    //     ]);

       
    //     $user = $request->user();
    //     $contacts = $user->emergencyContacts()->get(['name', 'phone_number']);

    //     $incident = Incident::create([
    //         'reporter_user_id' => $user->id,
    //         'meeting_id' => $validated['meeting_id'] ?? null,
    //         'type' => 'sos',
    //         'latitude' => $validated['latitude'],
    //         'longitude' => $validated['longitude'],
    //         'emergency_contacts_notified' => $contacts,
    //         'status' => 'open',
    //     ]);

    //     if (! empty($validated['meeting_id'])) {
    //         \App\Models\Meeting::where('id', $validated['meeting_id'])
    //             ->update(['status' => 'incident_reported']);
    //     }

    //     // TODO: integrate real SMS/push notification dispatch to trusted contacts here.
    //     // TODO (SOW "Future"): Law Enforcement Integration hook goes here once available.
    //     // foreach ($contacts as $contact) { app(NotificationService::class)->sendSos($contact, $incident); }

    //     return response()->json([
    //         'message' => 'Emergency SOS activated. Trusted contacts are being notified.',
    //         'incident' => $incident,
    //     ], 201);
    // }

    public function trigger(Request $request): JsonResponse
{
    $validated = $request->validate([
        'user_id' => ['required', 'integer', 'exists:users,id'],
        'meeting_id' => ['nullable', 'exists:meetings,id'],
        'latitude' => ['required', 'numeric'],
        'longitude' => ['required', 'numeric'],
    ]);

    $user = User::findOrFail($validated['user_id']);

    $contacts = $user->emergencyContacts()
        ->get(['full_name', 'phone_number']);

    $incident = Incident::create([
        'reporter_user_id' => $user->id,
        'meeting_id' => $validated['meeting_id'] ?? null,
        'type' => 'sos',
        'latitude' => $validated['latitude'],
        'longitude' => $validated['longitude'],
        'emergency_contacts_notified' => $contacts,
        'status' => 'open',
    ]);

    if (! empty($validated['meeting_id'])) {
        Meeting::where('id', $validated['meeting_id'])
            ->update(['status' => 'incident_reported']);
    }

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
