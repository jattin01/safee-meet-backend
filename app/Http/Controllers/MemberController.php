<?php

namespace App\Http\Controllers;

use App\Models\MemberSearchCount;
use App\Models\SearchHistory;
use App\Models\User;
use App\Services\PlanEntitlements;
use App\Support\Verification\VerificationLevelResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberController extends Controller
{
    /**
     * Search a member by their SAFEE PIN (safee_id).
     * GET /members/search?pin=SMXXXXXXXX
     */
    public function searchByPin(Request $request): JsonResponse
    {
        $pin = strtoupper(trim($request->query('pin', '')));

        if (strlen($pin) < 4) {
            return response()->json([
                'success' => false,
                'message' => 'PIN too short.',
            ], 422);
        }

        $user = User::where('safee_pin', $pin)
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }

        // Do not return own profile
        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot search yourself.',
            ], 422);
        }

        // Expired / cancelled trial → search is disabled until they pay.
        if (! app(PlanEntitlements::class)->subscriptionActive($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Your plan has expired. Subscribe to continue searching members.',
                'subscription_required' => true,
            ], 403);
        }

        if ($this->pinSearchLimitReached($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached your monthly SAFEE PIN search limit. Upgrade your plan to search more.',
                'required_feature' => 'pin_search',
            ], 403);
        }

        $this->logSearch($request, $user, $pin, 'pin');

        return response()->json([
            'success' => true,
            'data'    => $this->formatMember($user),
        ]);
    }

    /**
     * Search a member by QR code (also safee_id encoded in QR).
     * GET /members/qr?code=SMXXXXXXXX
     */
    public function searchByQR(Request $request): JsonResponse
    {
        $code = strtoupper(trim($request->query('code', '')));

        $user = User::where('safee_pin', $code)
            ->where('status', 'active')
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }

        if ($user->id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot search yourself.',
            ], 422);
        }

        $this->logSearch($request, $user, $code, 'qr');

        return response()->json([
            'success' => true,
            'data'    => $this->formatMember($user),
        ]);
    }

    /**
     * Members the current user has previously searched for (PIN or QR),
     * most recent first — lets the app show a "recently searched" list
     * that survives reinstalls, since it lives server-side. Backed by
     * `member_search_counts`, which holds exactly one (deduped) row per
     * searcher-member pair, so this is a plain indexed lookup rather than
     * a GROUP BY over the full search log.
     * GET /members/recent-searches
     */
    public function recentSearches(Request $request): JsonResponse
    {
        $rows = MemberSearchCount::where('searcher_id', $request->user()->id)
            ->orderByDesc('last_searched_at')
            ->take(20)
            ->get();

        $usersById = User::whereIn('id', $rows->pluck('member_id'))
            ->where('status', 'active')
            ->get()
            ->keyBy(fn (User $u) => (string) $u->id);

        $members = $rows
            ->map(fn (MemberSearchCount $row) => $usersById->get((string) $row->member_id))
            ->filter()
            ->map(fn (User $u) => $this->formatMember($u))
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $members,
        ]);
    }

    /**
     * Enforces the searcher's plan PIN-search quota, reset per calendar month.
     * Every PIN search action counts (including re-searching the same member),
     * counted from search_history for the current month. The allowance comes
     * from the plan_feature matrix ('pin_search') via PlanEntitlements:
     *   - null limit → Unlimited (no enforcement)
     *   - 0          → no plan / not entitled (blocked)
     */
    private function pinSearchLimitReached(User $searcher): bool
    {
        $limit = app(PlanEntitlements::class)->numericLimit($searcher, 'pin_search');

        if ($limit === null) {
            return false; // unlimited
        }

        $usedThisMonth = SearchHistory::where('searcher_id', $searcher->id)
            ->where('method', 'pin')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return $usedThisMonth >= $limit;
    }

    private function logSearch(Request $request, User $found, string $query, string $method): void
    {
        $searcherId = $request->user()->id;
        $now = now();

        SearchHistory::create([
            'searcher_id'   => $searcherId,
            'found_user_id' => $found->id,
            'query'         => $query,
            'method'        => $method,
        ]);

        DB::statement(
            'INSERT INTO member_search_counts (searcher_id, member_id, search_count, last_searched_at, created_at, updated_at)
             VALUES (?, ?, 1, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 search_count = search_count + 1,
                 last_searched_at = VALUES(last_searched_at),
                 updated_at = VALUES(updated_at)',
            [$searcherId, $found->id, $now, $now, $now],
        );
    }

    private function formatMember(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->display_name ?? 'SAFEE User',
            'safeePIN'          => $user->safee_pin,
            'avatarUrl'         => $user->avatar_url,
            'trustScore'        => (int) ($user->trust_score ?? 0),
            'verificationLevel' => VerificationLevelResolver::fromUser($user->kyc_status, $user->trust_tier),
            'subscriptionPlan'  => 'free',
            'rating'            => 0.0,
            'totalMeetings'     => 0,
            'badges'            => [],
        ];
    }
}
