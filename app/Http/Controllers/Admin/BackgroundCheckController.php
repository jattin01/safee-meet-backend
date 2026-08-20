<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BackgroundChecks\BackgroundCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BackgroundCheckController extends Controller
{
    public function recheck(
        Request $request,
        User $user,
        BackgroundCheckService $backgroundChecks,
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $result = $backgroundChecks->queueExplicitRecheck(
            $user,
            $request->user('admin'),
            $validated['reason'],
        );

        $queued = $result->reason === 'RECHECK_QUEUED';

        return response()->json([
            'message' => $queued ? 'Background re-check queued.' : 'Background re-check was not queued.',
            'data' => [
                'queued' => $queued,
                'reason' => $result->reason,
                'checkId' => $result->existingCheck?->id,
                'missingFields' => $result->missingFields,
            ],
        ], $queued ? 202 : 422);
    }
}
