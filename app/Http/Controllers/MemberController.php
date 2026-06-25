<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'success' => true,
            'data'    => $this->formatMember($user),
        ]);
    }

    private function formatMember(User $user): array
    {
        return [
            'id'                => $user->id,
            'name'              => $user->display_name ?? 'SAFEE User',
            'safeePIN'          => $user->safee_id,
            'avatarUrl'         => $user->avatar_url,
            'trustScore'        => (int) ($user->trust_score ?? 0),
            'verificationLevel' => $user->trust_tier ?? 'low',
            'subscriptionPlan'  => 'free',
            'rating'            => 0.0,
            'totalMeetings'     => 0,
            'badges'            => [],
        ];
    }
}
