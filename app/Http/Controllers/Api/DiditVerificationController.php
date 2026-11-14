<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\Verification\StoreDiditVerificationImages;
use App\Mail\VerificationApprovedMail;
use App\Mail\VerificationRejectedMail;
use App\Mail\VerificationUnderReviewMail;
use App\Models\UserVerification;
use App\Services\SafetyPointService;
use App\Services\Verification\DiditVerificationImageStorage;
use App\Services\Verification\UserVerificationLevelService;
use App\Support\Verification\TrustScoreCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class DiditVerificationController extends Controller
{
    /** Didit statuses that mean the user has not finished submitting. */
    private const UNSUBMITTED_STATUSES = ['Not Started', 'In Progress', 'Abandoned', 'Expired'];

    /** Didit statuses whose payload carries the user's completed evidence. */
    private const SUBMITTED_STATUSES = ['In Review', 'Approved', 'Declined'];

    private const IMAGE_FIELDS = [
        'face_id_image',
        'national_id_front_image',
        'national_id_back_image',
    ];

    /**
     * Fields that belong to one attempt and must not carry into the next.
     * Images are not listed: they stay (the selfie is also the profile
     * photo) until the new attempt is actually submitted.
     */
    private const PREVIOUS_ATTEMPT_FIELDS = [
        'national_id_number',
        'national_id_country',
        'rejection_reason',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'reviewed_by',
        'reviewed_by_admin_id',
    ];

    /**
     * Create a Didit verification session for the authenticated user and
     * return the hosted verification URL + session token for the SDK flow.
     */
    public function start(Request $request): JsonResponse
    {
        $user = $request->user();
        $existing = UserVerification::where('user_id', $user->id)->first();

        if ($existing?->status === 'approved') {
            return response()->json([
                'message' => 'Your identity is already verified.',
            ], 409);
        }

        if ($this->isAwaitingDecision($existing)) {
            return response()->json([
                'message' => 'Your verification has been submitted and is under review.',
            ], 409);
        }

        $callback = config('services.didit.callback_url')
            ?: url('/api/webhooks/didit');

        $response = Http::withHeaders([
            'X-Api-Key' => config('services.didit.api_key'),
            'Content-Type' => 'application/json',
        ])->post(rtrim(config('services.didit.base_url'), '/').'/v3/session/', [
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

        // Starting a session is not a submission: the user only counts as
        // submitted once Didit reports "In Review" or a decision. The earlier
        // attempt's review data is cleared now; its images are replaced when
        // this attempt is submitted (see handleWebhook).
        $verification = UserVerification::updateOrCreate(
            ['user_id' => $user->id],
            [
                'provider' => 'didit',
                'didit_session_id' => $data['session_id'] ?? null,
                'didit_decision_status' => $data['status'] ?? 'Not Started',
                'status' => 'not_submitted',
                'submitted_at' => null,
                'didit_payload' => [
                    'session_id' => $data['session_id'] ?? null,
                    'status' => $data['status'] ?? 'Not Started',
                ],
                ...array_fill_keys(self::PREVIOUS_ATTEMPT_FIELDS, null),
            ]
        );

        $user->forceFill([
            'kyc_status' => 'not_started',
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

        if (! $verification || ! $verification->didit_session_id) {
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

        if (! $secret || ! $timestampHeader || abs(now()->timestamp - (int) $timestampHeader) > 300) {
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

        if (! $signatureHeader || ! hash_equals($expected, $signatureHeader)) {
            Log::warning('Didit webhook signature mismatch');

            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        $sessionId = $payload['session_id'] ?? null;

        if (! $sessionId) {
            return response()->json(['message' => 'Missing session_id'], 422);
        }

        $verification = UserVerification::where('didit_session_id', $sessionId)->first();

        if (! $verification) {
            Log::warning('Didit webhook for unknown session', ['session_id' => $sessionId]);

            return response()->json(['message' => 'Unknown session'], 404);
        }

        $previousDiditStatus = $verification->didit_decision_status;
        $wasAlreadyApproved = $verification->status === 'approved';
        $wasAlreadyRejected = $verification->status === 'rejected';
        $diditStatus = $payload['status'] ?? $verification->didit_decision_status;

        // Webhooks can arrive out of order. Once Didit has decided, a late
        // progress or review event must not overwrite that decision.
        if (($wasAlreadyApproved || $wasAlreadyRejected)
            && ! in_array($diditStatus, ['Approved', 'Declined'], true)) {
            Log::info('Ignoring late Didit webhook for a decided verification', [
                'session_id' => $sessionId,
                'status' => $diditStatus,
                'current_status' => $verification->status,
            ]);

            return response()->json(['message' => 'ok']);
        }

        $verification->fill([
            'didit_decision_status' => $diditStatus,
            'didit_payload' => $payload,
        ]);

        match ($diditStatus) {
            'Approved' => $verification->fill([
                'status' => 'approved',
                'submitted_at' => $verification->submitted_at ?? now(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'rejection_reason' => null,
            ]),
            'Declined' => $verification->fill([
                'status' => 'rejected',
                'submitted_at' => $verification->submitted_at ?? now(),
                'reviewed_at' => now(),
                'rejected_at' => now(),
                'rejection_reason' => $payload['decision']['reason'] ?? 'Verification declined by Didit.',
            ]),
            'In Review' => $verification->fill([
                'status' => 'pending',
                'submitted_at' => $verification->submitted_at ?? now(),
            ]),
            // The user has not finished (or dropped out), so nothing counts
            // as submitted yet.
            'Not Started', 'In Progress', 'Abandoned', 'Expired' => $verification->fill([
                'status' => 'not_submitted',
                'submitted_at' => null,
            ]),
            default => null,
        };

        // The first submitted event of a session replaces the evidence of any
        // earlier attempt, so a retry with a different document never shows
        // one attempt's selfie or ID side next to another's.
        $staleImages = [];
        $isFirstSubmission = in_array($diditStatus, self::SUBMITTED_STATUSES, true)
            && ($previousDiditStatus === null || in_array($previousDiditStatus, self::UNSUBMITTED_STATUSES, true));

        if ($isFirstSubmission) {
            $storage = app(DiditVerificationImageStorage::class);

            foreach (self::IMAGE_FIELDS as $field) {
                $path = $verification->{$field};
                if (is_string($path) && $path !== '' && ! $storage->belongsToCurrentSession($verification, $path)) {
                    $staleImages[] = $path;
                    $verification->{$field} = null;
                }
            }
        }

        $verification->save();

        $this->deleteStoredImages($staleImages, $verification);

        if (in_array($diditStatus, ['Abandoned', 'Expired'], true) && $user = $verification->user) {
            $user->forceFill(['kyc_status' => 'not_started'])->save();
        } elseif ($diditStatus === 'In Review' && $user = $verification->user) {
            $user->forceFill(['kyc_status' => 'pending'])->save();
        }

        // Only a completed submission carries evidence for this attempt.
        // Partial images from an unfinished session are never stored.
        if (in_array($diditStatus, self::SUBMITTED_STATUSES, true)) {
            StoreDiditVerificationImages::dispatch($verification->id)->afterCommit();
        }

        if ($diditStatus === 'Approved' && ! $wasAlreadyApproved) {
            app(SafetyPointService::class)->addPoints(
                userId: $verification->user_id,
                eventKey: 'kyc_approved',
                points: 25,
                referenceType: 'user_verification',
                description: 'KYC verification approved by Didit.'
            );
        }

        if ($diditStatus === 'Declined' && ! $wasAlreadyRejected) {
            app(SafetyPointService::class)->addPoints(
                userId: $verification->user_id,
                eventKey: 'kyc_declined',
                points: 0,
                referenceType: 'user_verification',
                description: 'KYC verification declined by Didit.'
            );
        }

        if ($diditStatus === 'Approved' && $user = $verification->user) {
            DB::transaction(function () use ($verification, $user): void {
                $user->forceFill([
                    'kyc_status' => 'verified',
                    'kyc_verified_at' => now(),
                ])->save();

                app(UserVerificationLevelService::class)->promote(
                    $user,
                    'level1',
                    $verification,
                );
            });
        } elseif ($diditStatus === 'Declined' && $user = $verification->user) {
            $user->forceFill([
                'kyc_status' => 'rejected',
            ])->save();

            TrustScoreCalculator::recalculate($user);
        }

        if ($previousDiditStatus !== $diditStatus
            && ($user = $verification->user)
            && ! empty($user->email)) {
            $mail = match ($diditStatus) {
                'Approved' => new VerificationApprovedMail($user),
                'Declined' => new VerificationRejectedMail($user, $verification->rejection_reason),
                'In Review' => new VerificationUnderReviewMail($user),
                default => null,
            };

            if ($mail) {
                Mail::to($user->email)->queue($mail);
            }
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * A verification the user has actually submitted that is still waiting
     * for a decision: a Didit session in review, or a manual submission.
     * Starting a new session would overwrite it, so the user must wait.
     */
    private function isAwaitingDecision(?UserVerification $verification): bool
    {
        if ($verification?->status !== 'pending') {
            return false;
        }

        // Rows created before "not_submitted" existed were marked pending as
        // soon as a session started, so an unfinished Didit session must not
        // lock the user out of retrying.
        return $verification->provider !== 'didit'
            || ! in_array($verification->didit_decision_status, self::UNSUBMITTED_STATUSES, true);
    }

    /** @param list<string> $paths */
    private function deleteStoredImages(array $paths, UserVerification $verification): void
    {
        if ($paths === []) {
            return;
        }

        try {
            Storage::disk('public')->delete($paths);
        } catch (\Throwable $exception) {
            // The verification is already saved; leftover files must not
            // fail the webhook.
            Log::warning('Could not delete images from a previous verification attempt', [
                'verification_id' => $verification->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    protected function canonicalizePayload(string $rawBody): string
    {
        // Decode as objects so empty JSON maps remain `{}` instead of being
        // collapsed into `[]`, which would produce a different V3 signature.
        $payload = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
        $payload = $this->sortKeysRecursively($payload);

        return json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    protected function sortKeysRecursively(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);

            $sorted = new \stdClass;
            foreach ($properties as $key => $item) {
                $sorted->{$key} = $this->sortKeysRecursively($item);
            }

            return $sorted;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sortKeysRecursively($item);
            }
        }

        return $value;
    }
}
