<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeetingController extends Controller
{
    /**
     * GET /api/meetings — Recent Meetings list (Home screen + "See all")
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $meetings = Meeting::where('host_user_id', $userId)
            ->orWhere('guest_user_id', $userId)
            ->with(['host:id,name', 'guest:id,name'])
            ->latest('meeting_date')
            ->paginate(20);

        return response()->json($meetings);
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeParticipant($request, $meeting);

        return response()->json($meeting->load(['host:id,name', 'guest:id,name', 'locations']));
    }

    /**
     * POST /api/meetings — "Create Meeting" screen
     * (Meeting With / Date / Time / Meeting Purpose / Location / Notes)
     */
    public function store(Request $request): JsonResponse
    {
        
        $validated = $request->validate([
            'guest_user_id' => ['required', 'integer', 'exists:users,id'],
            'host_user_id' => ['required', 'integer', 'exists:users,id'],
            'meeting_date' => ['required', 'date'],
            'meeting_time' => ['required'],
            'location' => ['required', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'item_or_service' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', Rule::in([
                'coffee', 'marketplace', 'property', 'business', 'freelance', 'social', 'dating', 'other',
            ])],
        ]);

      

     

        $meeting = Meeting::create([
            'host_user_id' => $validated['host_user_id'],
            'guest_user_id' => $validated['guest_user_id'],
            'meeting_date' => $validated['meeting_date'],
            'meeting_time' => $validated['meeting_time'],
            'location' => $validated['location'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
            'item_or_service' => $validated['item_or_service'] ?? null,
            'type' => $validated['type'] ?? 'other',
            'status' => 'scheduled',
            'trust_score_snapshot' =>5,
        ]);


        return response()->json($meeting->load('guest:id,name,verification_level,trust_score'), 201);
    }

    public function destroy(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless(
            (int) $request->user()->id === (int) $meeting->host_user_id,
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

    /**
     * POST /api/meetings/{meeting}/arrive — arrival confirmation
     */
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
}
