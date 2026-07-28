<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingReview;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
        public function index()
        {
            return view('users.index');
        }

        /**
         * Paginated user rows for the users-management table (fetched via JS,
         * same pattern as the admins list). `plan` is eager-loaded because
         * plan_label reads the relation (avoids an N+1 across the page).
         */
        public function data(Request $request): JsonResponse
        {
            $validated = $request->validate([
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'in:10,25,50'],
            ]);

            $users = User::query()
                ->with('plan')
                ->latest('id')
                ->paginate($validated['per_page'] ?? 10)
                ->withQueryString();

            $users->getCollection()->transform(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name ?: $user->display_name ?: 'Unnamed User',
                'contact' => $user->email ?: $user->phone ?: '—',
                'initials' => $user->initials,
                'avatar_color' => $user->avatar_color,
                'safee_pin' => $user->safee_pin,
                'verification_label' => $user->verification_label,
                'verification_color' => $user->verification_color,
                'plan_label' => $user->plan_label,
                'trust_score' => $user->trust_score !== null ? round($user->trust_score) : null,
                'created_at' => $user->created_at,
                'status' => $user->status,
                'status_label' => $user->status_label,
                'status_color' => $user->status_color,
                'show_url' => route('users.show', $user->id),
            ]);

            return response()->json($users);
        }

        /**
         * Quick status change from the users list/profile (active/inactive/
         * suspended). Account deletion is intentionally not offered here —
         * it's a separate, more deliberate action.
         */
        public function updateStatus(Request $request, $id): JsonResponse
        {
            $validated = $request->validate([
                'status' => ['required', 'in:active,inactive,suspended'],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $user = User::findOrFail($id);

            $user->status = $validated['status'];

            if ($validated['status'] === 'suspended') {
                $user->account_suspended_at = now();
                $user->suspended_reason = $validated['reason'] ?? null;
            } else {
                $user->account_suspended_at = null;
                $user->suspended_reason = null;
            }

            $user->save();

            return response()->json([
                'status' => $user->status,
                'status_label' => $user->status_label,
                'status_color' => $user->status_color,
            ]);
        }

        public function show($id)
        {
            $user = User::with('emergencyContacts')->findOrFail($id);

            $meetingsQuery = Meeting::where('host_user_id', $user->id)
                ->orWhere('guest_user_id', $user->id);

            $meetings = (clone $meetingsQuery)
                ->with(['host', 'guest', 'reviews'])
                ->orderByDesc('meeting_date')
                ->limit(5)
                ->get();

            $meetingsCount = (clone $meetingsQuery)->count();

            $reviews = MeetingReview::where('reviewee_id', $user->id)
                ->with('reviewer')
                ->latest()
                ->limit(6)
                ->get();

            $averageRating = MeetingReview::where('reviewee_id', $user->id)->avg('rating');

            $subscription = Subscription::where('user_id', $user->id)
                ->with('plan')
                ->latest('started_at')
                ->first();

            $subscriptionPlan = $subscription?->plan;

            return view('users.show', [
                'user' => $user,
                'meetings' => $meetings,
                'meetingsCount' => $meetingsCount,
                'reviews' => $reviews,
                'averageRating' => $averageRating,
                'subscription' => $subscription,
                'subscriptionPlan' => $subscriptionPlan,
            ]);
        }
}
