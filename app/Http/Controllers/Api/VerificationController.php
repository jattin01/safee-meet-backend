<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\UserVerification;
use Illuminate\Support\Facades\DB;

class VerificationController extends Controller
{

    // public function submitVerification(Request $request): JsonResponse
    // {
    //     $validated = $request->validate([
    //         'face_id_image' => ['required','image','mimes:jpg,jpeg,png,webp','max:5120'],
    //         'national_id_front_image' => ['required','image','mimes:jpg,jpeg,png,webp','max:5120'],
    //         'national_id_back_image' => ['required','image','mimes:jpg,jpeg,png,webp','max:5120'],
    //         'national_id_number' => ['nullable','string','max:100'],
    //         'national_id_country' => ['nullable','string','max:100'],
    //     ]);

    //     $user = $request->user();

    //     $faceIdPath = $request
    //         ->file('face_id_image')
    //         ->store('verification/face-id', 'public');

    //     $nationalIdFrontPath = $request
    //         ->file('national_id_front_image')
    //         ->store('verification/national-id', 'public');

    //     $nationalIdBackPath = $request
    //         ->file('national_id_back_image')
    //         ->store('verification/national-id', 'public');

    //     $verification = UserVerification::updateOrCreate(
    //         [
    //             'user_id' => $user->id,
    //         ],
    //         [
    //             'face_id_image' => $faceIdPath,
    //             'national_id_front_image' => $nationalIdFrontPath,
    //             'national_id_back_image' => $nationalIdBackPath,
    //             'national_id_number' => $validated['national_id_number'] ?? null,
    //             'national_id_country' => $validated['national_id_country'] ?? null,

    //             // Approval ke baad hi Level 1 hoga
    //             'verification_level' => 0,
    //             'status' => 'pending',

    //             'reviewed_by' => null,
    //             'rejection_reason' => null,
    //             'submitted_at' => now(),
    //             'reviewed_at' => null,
    //             'approved_at' => null,
    //             'rejected_at' => null,
    //         ]
    //     );

    //     return response()->json([
    //         'status' => true,
    //         'message' => 'Verification documents submitted successfully.',
    //         'data' => $verification,
    //     ]);
    // }
    public function submitVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'face_id_image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'national_id_front_image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'national_id_back_image' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'national_id_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'national_id_country' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $existingVerification = UserVerification::where(
            'user_id',
            $validated['user_id']
        )->first();

        if ($existingVerification?->status === 'approved') {
            return response()->json([
                'status' => false,
                'message' => 'This user is already verified.',
            ], 422);
        }

        $uploadedPaths = [];

        try {
            DB::beginTransaction();

            $faceIdPath = $request
                ->file('face_id_image')
                ->store('verification/face-id', 'public');

            $uploadedPaths[] = $faceIdPath;

            $nationalIdFrontPath = $request
                ->file('national_id_front_image')
                ->store('verification/national-id', 'public');

            $uploadedPaths[] = $nationalIdFrontPath;

            $nationalIdBackPath = $request
                ->file('national_id_back_image')
                ->store('verification/national-id', 'public');

            $uploadedPaths[] = $nationalIdBackPath;

            /*
             * User dobara submit kare to purani images remove kar denge.
             */
            if ($existingVerification) {
                $oldImages = [
                    $existingVerification->face_id_image,
                    $existingVerification->national_id_front_image,
                    $existingVerification->national_id_back_image,
                ];

                foreach ($oldImages as $oldImage) {
                    if (
                        $oldImage &&
                        Storage::disk('public')->exists($oldImage)
                    ) {
                        Storage::disk('public')->delete($oldImage);
                    }
                }
            }

            $verification = UserVerification::updateOrCreate(
                [
                    'user_id' => $validated['user_id'],
                ],
                [
                    'face_id_image' => $faceIdPath,
                    'national_id_front_image' => $nationalIdFrontPath,
                    'national_id_back_image' => $nationalIdBackPath,

                    'national_id_number' =>
                        $validated['national_id_number'] ?? null,

                    'national_id_country' =>
                        $validated['national_id_country'] ?? null,

                    'verification_level' => 0,
                    'status' => 'pending',

                    'reviewed_by' => null,
                    'rejection_reason' => null,

                    'submitted_at' => now(),
                    'reviewed_at' => null,
                    'approved_at' => null,
                    'rejected_at' => null,
                ]
            );

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Verification documents submitted successfully.',
                'data' => [
                    'id' => $verification->id,
                    'user_id' => $verification->user_id,

                    'face_id_image' => asset(
                        'storage/' . $verification->face_id_image
                    ),

                    'national_id_front_image' => asset(
                        'storage/' .
                        $verification->national_id_front_image
                    ),

                    'national_id_back_image' => asset(
                        'storage/' .
                        $verification->national_id_back_image
                    ),

                    'national_id_number' =>
                        $verification->national_id_number,

                    'national_id_country' =>
                        $verification->national_id_country,

                    'verification_level' =>
                        $verification->verification_level,

                    'status' => $verification->status,
                    'submitted_at' => $verification->submitted_at,
                ],
            ], 201);
        } catch (\Throwable $exception) {
            DB::rollBack();

            foreach ($uploadedPaths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Unable to submit verification documents.',
            ], 500);
        }
    }

    public function approve(Request $request,UserVerification $verification): JsonResponse
    {
                if (
                    !$verification->face_id_image ||
                    !$verification->national_id_front_image ||
                    !$verification->national_id_back_image
                ) {
                    return response()->json([
                        'status' => false,
                        'message' => 'All required verification documents are not available.',
                    ], 422);
                }

                $verification->update([
                    'status' => 'approved',
                    'verification_level' => 1,
                    'reviewed_by' => $request->user()->id,
                    'rejection_reason' => null,
                    'reviewed_at' => now(),
                    'approved_at' => now(),
                    'rejected_at' => null,
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Verification approved and Level 1 assigned.',
                    'data' => $verification->fresh(),
                ]);
            }
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
