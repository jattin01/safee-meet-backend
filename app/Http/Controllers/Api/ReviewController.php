<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingReview;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * GET /api/v1/reviews
     * "Reviews & Ratings" screen: overall average, 5-star breakdown, sub-metric
     * percentages (Punctual/Trustworthy/Responsive), filterable list.
     *
     * Only the reviewee (who received the review) can view it on their profile —
     * the reviewer who gave it does not see it here.
     *
     * Query params: ?stars=5 | ?category=marketplace
     */
    public function index(Request $request): JsonResponse
    {
        $authUserId = $request->user()->id;

        $base = MeetingReview::where('reviewee_id', $authUserId);

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

        $reviews = $list->with(['reviewer:id,display_name as name,verification_level', 'meeting:id,type'])
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
        // Validate request
        $validated = $request->validate([
            'rating'       => ['required', 'integer', 'min:1', 'max:5'],
            'comment'      => ['nullable', 'string', 'max:2000'],
            'punctual'     => ['nullable', 'boolean'],
            'trustworthy'  => ['nullable', 'boolean'],
            'responsive'   => ['nullable', 'boolean'],
        ]);

        // The reviewer is always the authenticated caller, never a client-supplied
        // id — otherwise reviewer/reviewee can end up swapped (frontend has been
        // sending the reviewee's id here, inverting who received each review).
        $reviewerId = $request->user()->id;

        abort_unless(
            in_array($reviewerId, [$meeting->host_user_id, $meeting->guest_user_id]),
            403,
            'Not a participant in this meeting'
        );

        // Meeting must be completed before review
        if ($meeting->status !== 'completed') {
            return response()->json([
                'message' => 'Meeting must be completed before it can be reviewed'
            ], 422);
        }

        // Determine who is being reviewed
        $revieweeId = $meeting->host_user_id == $reviewerId
            ? $meeting->guest_user_id
            : $meeting->host_user_id;

        // Create or update review
        $review = MeetingReview::updateOrCreate(
            [
                'meeting_id'  => $meeting->id,
                'reviewer_id' => $reviewerId,
            ],
            [
                'reviewee_id'  => $revieweeId,
                'rating'       => $validated['rating'],
                'comment'      => $validated['comment'] ?? null,
                'punctual'     => $validated['punctual'] ?? null,
                'trustworthy'  => $validated['trustworthy'] ?? null,
                'responsive'   => $validated['responsive'] ?? null,
            ]
        );

        // Calculate average rating of the reviewee
        $avgRating = MeetingReview::where('reviewee_id', $revieweeId)
            ->avg('rating');

        // Update user's rating
        User::where('id', $revieweeId)
            ->update([
                'rating' => round($avgRating, 1),
            ]);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'data' => $review,
        ], 201);
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
