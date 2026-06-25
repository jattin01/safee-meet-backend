<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Verification\StoreIdentityDocumentRequest;
use App\Http\Requests\Verification\StoreSelfieVerificationRequest;
use App\Services\Verification\IdentityVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationApiController extends Controller
{
    public function __construct(
        private readonly IdentityVerificationService $verificationService,
    ) {}

    public function status(Request $request): JsonResponse
    {
        return response()->json(
            $this->verificationService->getStatus($request->user())
        );
    }

    public function progress(Request $request): JsonResponse
    {
        return response()->json(
            $this->verificationService->getProgress($request->user())
        );
    }

    public function uploadId(StoreIdentityDocumentRequest $request): JsonResponse
    {
        $verification = $this->verificationService->uploadIdDocument(
            user: $request->user(),
            front: $request->file('front'),
            back: $request->file('back'),
            documentType: $request->input('documentType'),
            issuingCountryCode: $request->input('issuingCountryCode'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Identity document uploaded successfully.',
            'data' => $this->verificationService->serializeProgress($verification),
        ], 201);
    }

    public function uploadSelfie(StoreSelfieVerificationRequest $request): JsonResponse
    {
        $verification = $this->verificationService->uploadSelfie(
            user: $request->user(),
            selfie: $request->file('selfie'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Selfie uploaded successfully. Verification is now pending review.',
            'data' => $this->verificationService->serializeProgress($verification),
        ], 201);
    }
}
