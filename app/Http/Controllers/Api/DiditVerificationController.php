<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use App\Support\Verification\TrustScoreCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiditVerificationController extends Controller
{
    /**
     * Create a Didit verification session for the authenticated user and
     * return the hosted verification URL for the mobile app to open.
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $response = Http::withHeaders([
            'X-Api-Key' => config('services.didit.api_key'),
        ])->post(rtrim(config('services.didit.base_url'), '/') . '/v2/session/', [
            'workflow_id' => config('services.didit.workflow_id'),
            'vendor_data' => (string) $user->id,
        ]);

        if ($response->failed()) {
            Log::error('Didit session creation failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json(['message' => 'Unable to start verification. Please try again.'], 502);
        }

        $data = $response->json();

        $verification = UserVerification::updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider' => 'didit',
                'didit_session_id' => $data['session_id'] ?? null,
                'didit_decision_status' => $data['status'] ?? 'Not Started',
                'status' => 'pending',
                'submitted_at' => now(),
            ]
        );

        return response()->json([
            'sessionId' => $verification->didit_session_id,
            'verificationUrl' => $data['url'] ?? null,
        ]);
    }

    /**
     * Report the latest known status for the authenticated user's
     * verification session (as last updated by the webhook).
     */
    public function status(Request $request): JsonResponse
    {
        $verification = UserVerification::where('user_id', $request->user()->id)->first();

        if (!$verification || !$verification->didit_session_id) {
            return response()->json(['status' => 'not_started']);
        }

        return response()->json([
            'status' => $verification->status,
            'diditStatus' => $verification->didit_decision_status,
            'rejectionReason' => $verification->rejection_reason,
            'submittedAt' => $verification->submitted_at?->toIso8601String(),
            'reviewedAt' => $verification->reviewed_at?->toIso8601String(),
        ]);
    }

    /**
     * Receive status/data update events pushed by Didit. Didit calls this
     * directly (no Sanctum token), so the shared webhook secret is the trust
     * boundary here instead of route middleware.
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $signature = $request->header('X-Signature');
        $secret = config('services.didit.webhook_secret');

        $expected = hash_hmac('sha256', $request->getContent(), (string) $secret);

        if (!$signature || !hash_equals($expected, $signature)) {
            Log::warning('Didit webhook signature mismatch');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $sessionId = $payload['session_id'] ?? null;

        if (!$sessionId) {
            return response()->json(['message' => 'Missing session_id'], 422);
        }

        $verification = UserVerification::where('didit_session_id', $sessionId)->first();

        if (!$verification) {
            Log::warning('Didit webhook for unknown session', ['session_id' => $sessionId]);

            return response()->json(['message' => 'Unknown session'], 404);
        }

        $diditStatus = $payload['status'] ?? $verification->didit_decision_status;

        $verification->fill([
            'didit_decision_status' => $diditStatus,
            'didit_payload' => $payload,
        ]);

        match ($diditStatus) {
            'Approved' => $verification->fill([
                'status' => 'approved',
                'reviewed_at' => now(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]),
            'Declined' => $verification->fill([
                'status' => 'rejected',
                'reviewed_at' => now(),
                'rejected_at' => now(),
                'rejection_reason' => $payload['decision']['reason'] ?? 'Verification declined by Didit.',
            ]),
            default => null,
        };

        $verification->save();

        if ($diditStatus === 'Approved' && $user = $verification->user) {
            $user->update([
                'kyc_status' => 'approved',
                'verification_level' => 'level1',
            ]);

            TrustScoreCalculator::recalculate($user);
        } elseif ($diditStatus === 'Declined' && $user = $verification->user) {
            $user->update(['kyc_status' => 'rejected']);

            TrustScoreCalculator::recalculate($user);
        }

        return response()->json(['message' => 'ok']);
    }
}
