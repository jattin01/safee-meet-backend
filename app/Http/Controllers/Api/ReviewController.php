<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * GET /api/v1/reviews
     * "Reviews & Ratings" screen: overall average, 5-star breakdown, sub-metric
     * percentages (Punctual/Trustworthy/Responsive), filterable list.
     *
     * Query params: ?stars=5 | ?category=marketplace | ?user_id=123 (defaults to auth user)
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->query('user_id', $request->user()->id);

        $base = MeetingReview::where('reviewee_id', $userId);

        // --- Summary block (average, count-per-star, sub-metric %) ---
        $all = (clone $base)->get();
        $total = $all->count();

        $breakdown = [];
        for ($star = 5; $star >= 1; $star--) {
            $breakdown[$star] = $all->where('rating', $star)->count();
        }

        $percentTrue = function (string $column) use ($all, $total) {
            if ($total === 0) {
                return 0;
            }

            return round(($all->where($column, true)->count() / $total) * 100);
        };

        // --- Filtered list ---
        $list = clone $base;

        if ($request->filled('stars')) {
            $list->where('rating', (int) $request->query('stars'));
        }

        if ($request->filled('category')) {
            $list->whereHas('meeting', fn ($q) => $q->where('type', $request->query('category')));
        }

        $reviews = $list->with(['reviewer:id,name,verification_level', 'meeting:id,type'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'average_rating' => $total ? round($all->avg('rating'), 1) : 0,
            'total_reviews' => $total,
            'breakdown' => $breakdown, // e.g. [5 => 41, 4 => 4, 3 => 2, 2 => 0, 1 => 0]
            'punctual_percent' => $percentTrue('punctual'),
            'trustworthy_percent' => $percentTrue('trustworthy'),
            'responsive_percent' => $percentTrue('responsive'),
            'reviews' => $reviews,
        ]);
    }

    /**
     * POST /api/v1/meetings/{meeting}/review — leave a review after a completed meeting
     */
    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        abort_unless(
            in_array($request->user()->id, [$meeting->host_user_id, $meeting->guest_user_id]),
            403,
            'Not a participant in this meeting'
        );

        if ($meeting->status !== 'completed') {
            return response()->json(['message' => 'Meeting must be completed before it can be reviewed'], 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'punctual' => ['nullable', 'boolean'],
            'trustworthy' => ['nullable', 'boolean'],
            'responsive' => ['nullable', 'boolean'],
        ]);

        $revieweeId = $meeting->host_user_id === $request->user()->id
            ? $meeting->guest_user_id
            : $meeting->host_user_id;

        $review = MeetingReview::updateOrCreate(
            ['meeting_id' => $meeting->id, 'reviewer_id' => $request->user()->id],
            $validated + ['reviewee_id' => $revieweeId]
        );

        // Keep the reviewee's rolling `rating` in sync for quick display on Home/Profile screens.
        $avg = MeetingReview::where('reviewee_id', $revieweeId)->avg('rating');
        \App\Models\User::where('id', $revieweeId)->update(['rating' => round($avg, 1)]);

        return response()->json($review, 201);
    }

    /**
     * POST /api/v1/reviews/{review}/helpful — "Helpful (12)" tap
     */
    public function markHelpful(MeetingReview $review): JsonResponse
    {
        $review->increment('helpful_count');

        return response()->json(['helpful_count' => $review->helpful_count]);
    }
}
