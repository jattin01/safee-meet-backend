<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\BackgroundChecks\EvaluateBackgroundCheckEligibility;
use App\Models\BackgroundCheck;
use App\Models\UserConsent;
use App\Services\BackgroundChecks\BackgroundCheckEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackgroundCheckController extends Controller
{
    public function consent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accepted' => ['required', 'boolean', 'accepted'],
        ]);

        $version = (string) config('services.searchbug.consent_version');
        $consent = UserConsent::where('user_id', $request->user()->id)
            ->activeBackgroundCheck()
            ->where('version', $version)
            ->latest('created_at')
            ->first();

        if (! $consent) {
            $consent = UserConsent::create([
                'user_id' => $request->user()->id,
                'consent_type' => UserConsent::CRIMINAL_BACKGROUND_CHECK,
                'version' => $version,
                'accepted' => $validated['accepted'],
                'accepted_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            ]);
        }

        EvaluateBackgroundCheckEligibility::dispatch($request->user()->id)->afterCommit();

        return response()->json([
            'success' => true,
            'message' => 'Criminal background-check consent recorded.',
            'data' => [
                'accepted' => true,
                'version' => $consent->version,
                'acceptedAt' => $consent->accepted_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function status(
        Request $request,
        BackgroundCheckEligibilityService $eligibility,
    ): JsonResponse {
        $check = BackgroundCheck::where('user_id', $request->user()->id)
            ->where('check_type', 'criminal')
            ->latest('created_at')
            ->first();

        $eligibilityResult = $eligibility->evaluate($request->user());

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => $eligibilityResult->eligible,
                'eligibilityReason' => $eligibilityResult->reason,
                'missingFields' => $eligibilityResult->missingFields,
                'check' => $check ? [
                    'id' => $check->id,
                    'status' => $check->status,
                    'providerStatus' => $check->provider_status,
                    'resultClassification' => $check->result_classification,
                    'summary' => $check->result_summary,
                    'requestedAt' => $check->requested_at?->toIso8601String(),
                    'completedAt' => $check->completed_at?->toIso8601String(),
                    'expiresAt' => $check->expires_at?->toIso8601String(),
                    'failureCode' => $check->failure_code,
                ] : null,
            ],
        ]);
    }
}
