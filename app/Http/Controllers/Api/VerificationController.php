<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    /**
     * GET /api/verification/status
     * Matches the "Verification Status" screen: trust score, badges, per-level checklist.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'trust_score' => $user->trust_score,
            'rating' => $user->rating,
            'safee_pin' => $user->safee_pin,
            'badges' => array_filter([
                $user->verification_level !== 'none' ? 'level1_verified' : null,
                in_array($user->verification_level, ['level2', 'professional']) ? 'level2_verified' : null,
                $user->verification_level === 'professional' ? 'verified_professional' : null,
            ]),
            'requests' => $user->verificationRequests()->latest()->get(),
        ]);
    }

    /**
     * Identity Verification Step 1 — Upload ID (front + back)
     * POST /api/verification/level1/id
     */
    public function uploadId(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'drivers_license_front' => ['required', 'image', 'max:8192'],
            'drivers_license_back' => ['required', 'image', 'max:8192'],
        ]);

        $user = $request->user();

        $frontPath = $validated['drivers_license_front']->store('verification/license', 'public');
        $backPath = $validated['drivers_license_back']->store('verification/license', 'public');

        // TODO: integrate real OCR vendor (e.g. Onfido, Jumio) here instead of this stub.
        $ocrResult = ['name' => $user->name, 'dob' => null, 'address' => null, 'id_number' => null];

        $verification = VerificationRequest::updateOrCreate(
            ['user_id' => $user->id, 'level' => 'level1', 'status' => 'pending'],
            [
                'drivers_license_front' => $frontPath,
                'drivers_license_back' => $backPath,
                'ocr_extracted' => $ocrResult,
            ]
        );

        return response()->json([
            'message' => 'ID uploaded — proceed to selfie step',
            'verification_id' => $verification->id,
            'next_step' => 'selfie',
        ], 201);
    }

    /**
     * Identity Verification Step 2 — Selfie (face match / liveness / anti-spoof)
     * Step 3 "Processing" is the client polling GET /verification/level1/{id}/status afterward.
     * POST /api/verification/level1/{verification}/selfie
     */
    public function uploadSelfie(Request $request, VerificationRequest $verification): JsonResponse
    {
        abort_unless($verification->user_id === $request->user()->id, 403);

        $validated = $request->validate(['selfie' => ['required', 'image', 'max:8192']]);

        $selfiePath = $validated['selfie']->store('verification/selfie', 'public');

        // TODO: integrate real face-match/liveness/anti-spoof vendor here instead of this stub.
        // $faceResult = app(FaceVerificationService::class)->verify($selfiePath, $verification->drivers_license_front);
        $verification->update([
            'selfie' => $selfiePath,
            'face_match_passed' => null,   // set by vendor webhook/response
            'liveness_check_passed' => null,
            'anti_spoof_passed' => null,
        ]);

        return response()->json([
            'message' => 'Selfie submitted — processing',
            'verification_id' => $verification->id,
            'next_step' => 'processing',
        ]);
    }

    /**
     * Identity Verification Step 3/4 — Processing / Complete (client polls this)
     * GET /api/verification/level1/{verification}/status
     */
    public function level1Status(Request $request, VerificationRequest $verification): JsonResponse
    {
        abort_unless($verification->user_id === $request->user()->id, 403);

        // Step is "processing" until status flips to approved/rejected by the admin
        // (or automatically once a real face-match vendor responds — see uploadSelfie TODO).
        $step = match (true) {
            $verification->status === 'approved' => 'complete',
            $verification->status === 'rejected' => 'rejected',
            $verification->selfie === null => 'selfie',
            default => 'processing',
        };

        return response()->json([
            'step' => $step,
            'verification' => $verification,
        ]);
    }

    /**
     * Legacy single-shot submission (kept for non-wizard clients / API testing).
     * POST /api/verification/level1
     */
    public function submitLevel1(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'drivers_license_front' => ['required', 'image', 'max:8192'],
            'drivers_license_back' => ['required', 'image', 'max:8192'],
            'selfie' => ['required', 'image', 'max:8192'],
        ]);

        $user = $request->user();

        $frontPath = $validated['drivers_license_front']->store('verification/license', 'public');
        $backPath = $validated['drivers_license_back']->store('verification/license', 'public');
        $selfiePath = $validated['selfie']->store('verification/selfie', 'public');

        // TODO: integrate real OCR vendor (e.g. Onfido, Jumio) here instead of this stub.
        // $ocrResult = app(OcrService::class)->extract($frontPath, $backPath);
        $ocrResult = [
            'name' => $user->name,
            'dob' => null,
            'address' => null,
            'id_number' => null,
        ];

        // TODO: integrate real face-match/liveness/anti-spoof vendor here instead of this stub.
        // $faceResult = app(FaceVerificationService::class)->verify($selfiePath, $frontPath);
        $faceResult = [
            'face_match_passed' => null,
            'liveness_check_passed' => null,
            'anti_spoof_passed' => null,
        ];

        $verification = VerificationRequest::create([
            'user_id' => $user->id,
            'level' => 'level1',
            'status' => 'pending',
            'drivers_license_front' => $frontPath,
            'drivers_license_back' => $backPath,
            'selfie' => $selfiePath,
            'ocr_extracted' => $ocrResult,
            'face_match_passed' => $faceResult['face_match_passed'],
            'liveness_check_passed' => $faceResult['liveness_check_passed'],
            'anti_spoof_passed' => $faceResult['anti_spoof_passed'],
        ]);

        return response()->json([
            'message' => 'Level 1 verification submitted for review',
            'verification' => $verification,
        ], 201);
    }

    /**
     * Step 5 (Premium upsell) — Level 2 Soft Background Check
     * POST /api/verification/level2
     */
    public function submitLevel2(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->verification_level === 'none') {
            return response()->json(['message' => 'Complete Level 1 verification first'], 422);
        }

        $verification = VerificationRequest::create([
            'user_id' => $user->id,
            'level' => 'level2',
            'status' => 'pending',
            // TODO: integrate real background-check vendor; this stub just opens the request.
            'background_check_result' => null,
        ]);

        return response()->json([
            'message' => 'Level 2 background verification requested',
            'verification' => $verification,
        ], 201);
    }

    /**
     * Professional Verification (Realtors, Insurance Agents, Contractors, etc.)
     * POST /api/verification/professional
     */
    public function submitProfessional(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'business_license' => ['required', 'file', 'max:8192'],
            'professional_credentials' => ['nullable', 'file', 'max:8192'],
            'insurance_document' => ['nullable', 'file', 'max:8192'],
        ]);

        $user = $request->user();

        $verification = VerificationRequest::create([
            'user_id' => $user->id,
            'level' => 'professional',
            'status' => 'pending',
            'business_license' => $validated['business_license']->store('verification/professional', 'public'),
            'professional_credentials' => isset($validated['professional_credentials'])
                ? $validated['professional_credentials']->store('verification/professional', 'public') : null,
            'insurance_document' => isset($validated['insurance_document'])
                ? $validated['insurance_document']->store('verification/professional', 'public') : null,
        ]);

        return response()->json([
            'message' => 'Professional verification submitted for review',
            'verification' => $verification,
        ], 201);
    }
}
