<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\MeetingReview;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
        public function index()
        {
            $users = User::latest()->paginate(20);
            $totalUsers = User::count();

            return view('users.index', [
                'users' => $users,
                'totalUsers' => $totalUsers,
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
