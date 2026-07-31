<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookDebugController extends Controller
{
    /**
     * No auth, no signature check — just logs whatever hits it, so we can
     * confirm Stripe (or anything else) can actually reach this server.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Log::info('Webhook debug hit', [
            'method' => $request->method(),
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
        ]);

        return response()->json(['message' => 'logged']);
    }
}
