<?php

namespace App\Http\Controllers;

use App\Models\MemberSearchCount;
use App\Models\SearchHistory;
use App\Models\User;
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

        $user = User::where('safee_id', $pin)
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

        if ($this->pinSearchLimitReached($request->user(), $user)) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached your monthly SAFEE PIN search limit. Upgrade your plan to search more members.',
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

        $user = User::where('safee_id', $code)
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
     * Logs every search attempt (for full audit/analytics in `search_history`)
     * and atomically upserts the deduped `member_search_counts` row for this
     * searcher-member pair — a repeat search of the same member updates the
     * existing row's count/timestamp instead of counting again.
     */
    /**
     * Enforces the searcher's plan PIN-search quota, reset per calendar month.
     * The quota counts DISTINCT members searched via PIN this month, sourced
     * from search_history — so re-searching someone you already looked up this
     * month is free, and the count resets naturally on the 1st.
     *
     * A null pin_search_limit (or no plan) means unlimited — no enforcement.
     */
    private function pinSearchLimitReached(User $searcher, User $target): bool
    {
        $limit = $searcher->plan?->pin_search_limit;

        if ($limit === null) {
            return false; // unlimited plan (or no plan set)
        }

        $monthStart = now()->startOfMonth();

        // Already searched this member this month → doesn't consume new quota.
        $alreadyThisMonth = SearchHistory::where('searcher_id', $searcher->id)
            ->where('found_user_id', $target->id)
            ->where('method', 'pin')
            ->where('created_at', '>=', $monthStart)
            ->exists();

        if ($alreadyThisMonth) {
            return false;
        }

        $distinctThisMonth = SearchHistory::where('searcher_id', $searcher->id)
            ->where('method', 'pin')
            ->where('created_at', '>=', $monthStart)
            ->distinct()
            ->count('found_user_id');

        return $distinctThisMonth >= $limit;
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
            'safeePIN'          => $user->safee_id,
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
