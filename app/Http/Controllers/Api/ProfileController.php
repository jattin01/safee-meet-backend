<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * GET /api/home
     * Matches the Home screen: greeting, trust score, quick stats, quick actions data, recent meetings.
     */
    public function home(Request $request): JsonResponse
    {   
            // $user = \App\Models\User::findOrFail($request->user_id);
        
        //  dd($request->user());
         $user = $request->user();

        $meetingsCount = $user->meetingCount();

        $recentMeetings = Meeting::where('host_user_id', $user->id)
            ->orWhere('guest_user_id', $user->id)
            ->with(['host:id,name', 'guest:id,name'])
            ->latest('meeting_date')
            ->take(5)
            ->get();

        return response()->json([
            'greeting' => 'Good morning',
            'name' => $user->name,
            'badge' => $user->badge,
            'verification_level' => $user->verification_level,
            'trust_score' => $user->trust_score,
            'meetings_count' => $meetingsCount,
            'rating' => $user->rating,
            'safee_pin' => $user->safee_pin,
            'unread_notifications' => 0, // TODO: wire to a notifications table if/when built
            'recent_meetings' => $recentMeetings,
        ]);
    }

    //code before changes 
    // public function home(Request $request): JsonResponse
    // {
    //     $user = $request->user();

    //     $meetingsCount = $user->hostedMeetings()->count() + $user->guestMeetings()->count();

    //     $recentMeetings = $user->hostedMeetings()
    //         ->with('guest:id,name')
    //         ->orWhere('guest_user_id', $user->id)
    //         ->latest('meeting_date')
    //         ->take(5)
    //         ->get();

    //     return response()->json([
    //         'greeting' => 'Good morning',
    //         'name' => $user->name,
    //         'badge' => $user->badge,
    //         'verification_level' => $user->verification_level,
    //         'trust_score' => $user->trust_score,
    //         'meetings_count' => $meetingsCount,
    //         'rating' => $user->rating,
    //         'safee_pin' => $user->safee_pin,
    //         'unread_notifications' => 0, // TODO: wire to a notifications table if/when built
    //         'recent_meetings' => $recentMeetings,
    //     ]);
        
    // }

    /**
     * GET /api/profile
     * Matches "My Profile" screen.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load(['plan', 'activeSubscription.plan']);
        $planSlug = $user->plan?->slug;

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'badges' => array_filter([
                $user->verification_level !== 'none' ? 'Level 1' : null,
                in_array($user->verification_level, ['level2', 'professional']) ? 'Level 2' : null,
                $planSlug === 'premium' ? 'Premium' : null,
                $planSlug === 'professional' ? 'Professional' : null,
            ]),
            'safee_pin' => $user->safee_pin,
            'meetings_count' => $user->meetingCount(),
            'trust_score' => $user->trust_score,
            'rating' => $user->rating,
            'current_plan' => $user->plan,
            'subscription' => $user->activeSubscription,
        ]);
    }

    /**
     * PUT /api/profile
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,'.$request->user()->id],
        ]);

        $request->user()->update($validated);

        return response()->json(['message' => 'Profile updated', 'user' => $request->user()]);
    }

    /**
     * GET /api/trusted-contacts
     */
    public function trustedContacts(Request $request): JsonResponse
    {
        return response()->json($request->user()->trustedContacts);
    }

    /**
     * POST /api/trusted-contacts
     */
    public function addTrustedContact(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'relationship' => ['nullable', 'string', 'max:100'],
        ]);

        $contact = $request->user()->trustedContacts()->create($validated);

        return response()->json($contact, 201);
    }

    /**
     * DELETE /api/trusted-contacts/{trustedContact}
     */
    public function removeTrustedContact(Request $request, int $trustedContact): JsonResponse
    {
        $request->user()->trustedContacts()->where('id', $trustedContact)->delete();

        return response()->json(['message' => 'Contact removed']);
    }
}
