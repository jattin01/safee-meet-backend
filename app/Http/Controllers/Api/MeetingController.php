<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeetingController extends Controller
{
    public function __construct(private readonly PushNotificationService $push)
    {
    }

    /**
     * GET /api/meetings — Recent Meetings list (Home screen + "See all")
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $meetings = Meeting::where('host_user_id', $userId)
            ->orWhere('guest_user_id', $userId)
            ->with(['host:id,display_name,trust_score,trust_tier', 'guest:id,display_name,trust_score,trust_tier'])
            ->latest('meeting_date')
            ->paginate(20);

        return response()->json($meetings);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);

        return response()->json($meeting->load([
            'host:id,display_name,trust_score,trust_tier',
            'guest:id,display_name,trust_score,trust_tier',
            'locations',
        ]));
    }

    /**
     * POST /api/meetings — "Create Meeting" screen
     * (Meeting With / Date / Time / Meeting Purpose / Location / Notes)
     */
    public function emergencyShare(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);

        abort_unless($meeting->status === 'scheduled', 422, 'Only scheduled meetings can be shared.');

        $meeting->load([
            'host:id,name,display_name,phone',
            'guest:id,name,display_name,phone',
        ]);

        $emergencyContacts = $request->user()
            ->emergencyContacts()
            ->get(['id', 'full_name', 'relationship', 'phone_number']);

        return response()->json([
            'success' => true,
            'data' => [
                'meeting' => [
                    'id' => $meeting->id,
                    'reference' => $meeting->reference,
                    'status' => $meeting->status,
                    'meeting_date' => $meeting->meeting_date?->toDateString(),
                    'meeting_time' => $meeting->meeting_time,
                    'location' => $meeting->location,
                    'latitude' => $meeting->latitude,
                    'longitude' => $meeting->longitude,
                    'purpose' => $meeting->purpose,
                ],
                'users' => [
                    'host' => [
                        'id' => $meeting->host?->id,
                        'name' => $meeting->host?->name ?: $meeting->host?->display_name ?: 'SAFEE User',
                        'phone' => $meeting->host?->phone,
                        'latitude' => $meeting->host?->latitude,
                        'longitude' => $meeting->host?->longitude,
                    ],
                    'guest' => [
                        'id' => $meeting->guest?->id,
                        'name' => $meeting->guest?->name ?: $meeting->guest?->display_name ?: 'SAFEE User',
                        'phone' => $meeting->guest?->phone,
                        'latitude' => $meeting->guest?->latitude,
                        'longitude' => $meeting->guest?->longitude,
                    ],
                ],
                'emergency_contacts' => $emergencyContacts,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'guest_user_id' => ['required', 'string', 'exists:users,id'],
            'meeting_date' => ['required', 'date', 'after_or_equal:today'],
            'meeting_time' => ['required', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'item_or_service' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in([
                'coffee', 'marketplace', 'property', 'business', 'freelance', 'social', 'dating', 'other',
            ])],
        ]);

        // The host is whoever is authenticated — never trust a client-supplied id here.
        $hostId = $request->user()->id;

        if ($validated['guest_user_id'] === $hostId) {
            throw ValidationException::withMessages([
                'guest_user_id' => 'You cannot create a meeting with yourself.',
            ]);
        }

        $startAt = \Illuminate\Support\Carbon::parse($validated['meeting_date'].' '.$validated['meeting_time']);

        if ($startAt->isPast()) {
            throw ValidationException::withMessages([
                'meeting_time' => 'You cannot create a meeting in the past.',
            ]);
        }

        $meeting = Meeting::create([
            'host_user_id' => $hostId,
            'guest_user_id' => $validated['guest_user_id'],
            // "title"/"scheduled_start_at" predate this API and are still
            // required by the table — derive them from the new fields.
            'title' => $validated['purpose'] ?? 'Meeting',
            'scheduled_start_at' => $startAt,
            'meeting_date' => $validated['meeting_date'],
            'meeting_time' => $validated['meeting_time'],
            'location' => $validated['location'],
            'planned_address' => $validated['location'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'planned_latitude' => $validated['latitude'] ?? null,
            'planned_longitude' => $validated['longitude'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
            'item_or_service' => $validated['item_or_service'] ?? null,
            'type' => $validated['type'] ?? 'other',
            'status' => 'pending_approval',
            'trust_score_snapshot' => $request->user()->trust_score,
        ]);

        $meeting->load(['host', 'guest']);
        $when = $this->formatWhen($meeting);

        $this->push->sendToUser(
            $meeting->guest,
            'New meeting request',
            "{$meeting->host->display_name} wants to meet — {$meeting->location}, {$when}",
            ['type' => 'meeting_request', 'meeting_id' => (string) $meeting->id],
        );
        $this->push->sendToUser(
            $meeting->host,
            'Request sent',
            "Waiting for {$meeting->guest->display_name} to respond",
            ['type' => 'meeting_confirmed', 'meeting_id' => (string) $meeting->id],
        );

        return response()->json([
            'success' => true,
            'message' => 'Meeting created successfully.',
            'data' => ['meeting_id' => $meeting->id],
        ], 201);
    }

    private function formatWhen(Meeting $meeting): string
    {
        $date = $meeting->meeting_date?->format('M j, Y') ?? '';
        $time = $meeting->meeting_time ?? '';

        return trim("{$date} {$time}");
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless(
            $request->user()->id === $meeting->host_user_id,
            403,
            'Only the meeting host can delete this meeting'
        );

        $meeting->delete();

        return response()->json(['message' => 'Meeting deleted successfully.']);
    }

    /**
     * POST /api/meetings/{meeting}/accept
     */
    public function accept(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);
        $meeting->update(['status' => 'live']);

        return response()->json($meeting);
    }

    /**
     * POST /api/meetings/{meeting}/approve — guest approves a pending
     * meeting request. Guest-only; distinct from accept() ("live now").
     */
    public function approve(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeGuest($request, $meeting);
        abort_unless($meeting->status === 'pending_approval', 422, 'Meeting is not awaiting approval');

        $meeting->update(['status' => 'scheduled']);
        $meeting->load(['host', 'guest']);

        $this->push->sendToUser(
            $meeting->host,
            'Meeting approved',
            "{$meeting->guest->display_name} confirmed — {$this->formatWhen($meeting)} at {$meeting->location}",
            ['type' => 'meeting_approved', 'meeting_id' => (string) $meeting->id],
        );

        return response()->json($meeting);
    }

    /**
     * POST /api/meetings/{meeting}/deny — guest declines a pending
     * meeting request. Guest-only.
     */
    public function deny(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeGuest($request, $meeting);
        abort_unless($meeting->status === 'pending_approval', 422, 'Meeting is not awaiting approval');

        $meeting->update(['status' => 'declined']);
        $meeting->load(['host', 'guest']);

        $this->push->sendToUser(
            $meeting->host,
            'Meeting declined',
            "{$meeting->guest->display_name} declined the meeting request",
            ['type' => 'meeting_declined', 'meeting_id' => (string) $meeting->id],
        );

        return response()->json($meeting);
    }

    /**
     * POST /api/meetings/{meeting}/reschedule
     */
    public function reschedule(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);

        $validated = $request->validate([
            'meeting_date' => ['required', 'date'],
            'meeting_time' => ['required'],
        ]);

        $meeting->update($validated + ['status' => 'scheduled']);

        return response()->json($meeting);
    }

    /**
     * POST /api/meetings/{meeting}/cancel
     */
    public function cancel(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);
        $meeting->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Meeting cancelled']);
    }

    
    public function arrive(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);
        $meeting->update(['arrived_at' => now()]);

        return response()->json(['message' => 'Arrival confirmed']);
    }

    /**
     * POST /api/meetings/{meeting}/location — Live Location Sharing ping
     */
    public function pingLocation(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);

        $validated = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $location = $meeting->locations()->create([
            'user_id' => $request->user()->id,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'recorded_at' => now(),
        ]);

        return response()->json($location, 201);
    }

    /**
     * POST /api/meetings/{meeting}/complete — "Safe Meeting Completed"
     */
    public function complete(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);

        $validated = $request->validate([
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ]);

        $meeting->update(['status' => 'completed']);

        // TODO: persist per-meeting ratings to a `meeting_reviews` table if star ratings
        // (as seen on the Home screen's Recent Meetings list) need individual history
        // rather than a rolling average on users.rating.

        return response()->json(['message' => 'Meeting marked complete']);
    }

    private function authorizeParticipant(Request $request, Meeting $meeting): void
    {
        abort_unless(
            in_array($request->user()->id, [$meeting->host_user_id, $meeting->guest_user_id]),
            403,
            'Not a participant in this meeting'
        );
    }

    private function authorizeGuest(Request $request, Meeting $meeting): void
    {
        abort_unless(
            $request->user()->id === $meeting->guest_user_id,
            403,
            'Only the invited guest can respond to this meeting request'
        );
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric'],
            'longitude' => ['required', 'numeric'],
        ]);

        $user = $request->user();
        $user->latitude = $validated['latitude'];
        $user->longitude = $validated['longitude'];
        $user->save();

        return response()->json(['message' => 'Location updated successfully.']);
    }
}
