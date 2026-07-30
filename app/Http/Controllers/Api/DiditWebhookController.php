<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DiditWebhookController extends Controller
{
    /**
     * Handle incoming Didit webhooks and log full payload for inspection.
     * No auth middleware — trust boundary should be implemented if needed.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = [
            'headers' => $request->headers->all(),
            'query' => $request->query(),
            'body' => $request->all(),
            'raw' => $request->getContent(),
            'files' => array_map(function ($f) {
                try {
                    return is_array($f) ? array_map(fn($i) => $i->getClientOriginalName(), $f) : $f->getClientOriginalName();
                } catch (\Throwable $e) {
                    return null;
                }
            }, $request->allFiles()),
        ];

        // Primary app log
        Log::info('Didit webhook received', $payload);

        // Append to dedicated didit webhook log for easy grepping/inspection
        try {
            $line = json_encode(['received_at' => now()->toIso8601String(), 'payload' => $payload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents(storage_path('logs/didit-webhooks.log'), $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            Log::warning('Failed writing didit webhook log file', ['error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'ok']);
    }
}
