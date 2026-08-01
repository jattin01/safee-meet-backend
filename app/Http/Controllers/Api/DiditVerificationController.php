<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use App\Support\Verification\TrustScoreCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiditVerificationController extends Controller
{
    /**
     * Create a Didit verification session for the authenticated user and
     * return the hosted verification URL + session token for the SDK flow.
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();

        $callback = config('services.didit.callback_url')
            ?: url('/api/webhooks/didit');

        $response = Http::withHeaders([
            'X-Api-Key' => config('services.didit.api_key'),
            'Content-Type' => 'application/json',
        ])->post(rtrim(config('services.didit.base_url'), '/') . '/v3/session/', [
            'workflow_id' => config('services.didit.workflow_id'),
            'callback' => $callback,
            'vendor_data' => (string) $user->id,
        ]);

        if ($response->failed()) {
            Log::error('Didit session creation failed', [
                'user_id' => $user->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'message' => 'Unable to start verification. Please try again.',
            ], 502);
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
                'didit_payload' => [
                    'session_id' => $data['session_id'] ?? null,
                    'status' => $data['status'] ?? 'Not Started',
                ],
            ]
        );

        $user->forceFill([
            'kyc_status' => 'pending',
        ])->save();

        return response()->json([
            'sessionId' => $verification->didit_session_id,
            'sessionToken' => $data['session_token'] ?? null,
            'verificationUrl' => $data['verification_url'] ?? ($data['url'] ?? null),
            'status' => $data['status'] ?? 'Not Started',
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
        $rawBody = $request->getContent();
        $secret = (string) config('services.didit.webhook_secret');
        $signatureHeader = $request->header('X-Signature-V2') ?: $request->header('X-Signature');
        $timestampHeader = $request->header('X-Timestamp');

        if ($timestampHeader && abs(now()->timestamp - (int) $timestampHeader) > 300) {
            Log::warning('Didit webhook timestamp too old', [
                'timestamp' => $timestampHeader,
            ]);

            return response()->json(['message' => 'Expired webhook timestamp'], 401);
        }

        if ($request->hasHeader('X-Signature-V2')) {
            $expected = hash_hmac('sha256', $this->canonicalizePayload($rawBody), $secret);
        } else {
            $expected = hash_hmac('sha256', $rawBody, $secret);
        }

        if (!$signatureHeader || !hash_equals($expected, $signatureHeader)) {
            Log::warning('Didit webhook signature mismatch');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
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
            'In Review' => $verification->fill([
                'status' => 'pending',
            ]),
            'In Progress', 'Abandoned', 'Expired' => $verification->fill([
                'status' => 'pending',
            ]),
            default => null,
        };

        $verification->save();

        if ($diditStatus === 'Approved' && $user = $verification->user) {
            $user->forceFill([
                'kyc_status' => 'approved',
                'kyc_verified_at' => now(),
            ])->save();

            TrustScoreCalculator::recalculate($user);
        } elseif ($diditStatus === 'Declined' && $user = $verification->user) {
            $user->forceFill([
                'kyc_status' => 'rejected',
            ])->save();

            TrustScoreCalculator::recalculate($user);
        }

        return response()->json(['message' => 'ok']);
    }

    protected function canonicalizePayload(string $rawBody): string
    {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $payload = $this->sortKeysRecursively($payload);

        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    protected function sortKeysRecursively(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortKeysRecursively($item);
            }
        }

        return $value;
    }
}
