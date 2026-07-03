<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PinController extends Controller
{
    /**
     * GET /api/search/pin/{pin}
     * "Search Member" screen — SAFEE PIN tab. Logs to Recent Searches.
     */
    public function findByPin(Request $request, string $pin): JsonResponse
    {
        return $this->lookup($request, $pin, 'pin');
    }

    /**
     * GET /api/search/qr/{code}
     * "Search Member" screen — QR Code tab.
     */
    public function findByQr(Request $request, string $code): JsonResponse
    {
        return $this->lookup($request, $code, 'qr');
    }

    /**
     * GET /api/search/recent — "RECENT SEARCHES" list on the Search Member screen
     */
    public function recent(Request $request): JsonResponse
    {
        $recent = SearchHistory::where('searcher_id', $request->user()->id)
            ->whereNotNull('found_user_id')
            ->with('foundUser:id,name,safee_pin,verification_level,badge')
            ->latest()
            ->limit(10)
            ->get()
            ->pluck('foundUser')
            ->unique('id')
            ->values();

        return response()->json($recent);
    }

    private function lookup(Request $request, string $query, string $method): JsonResponse
    {
        $user = User::where('safee_pin', $query)->first();

        SearchHistory::create([
            'searcher_id' => $request->user()->id,
            'found_user_id' => $user?->id,
            'query' => $query,
            'method' => $method,
        ]);

        if (! $user) {
            return response()->json(['message' => 'No member found with that PIN'], 404);
        }

        return response()->json([
            'name' => $user->name,
            'safee_pin' => $user->safee_pin,
            'verification_level' => $user->verification_level,
            'badge' => $user->badge,
            'trust_score' => $user->trust_score,
            'rating' => $user->rating,
            'account_type' => $user->account_type,
            'company_name' => $user->company_name,
        ]);
    }
}
