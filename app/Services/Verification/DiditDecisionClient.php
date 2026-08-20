<?php

namespace App\Services\Verification;

use App\Exceptions\BackgroundCheckProviderException;
use Illuminate\Support\Facades\Http;

class DiditDecisionClient
{
    /** @return array<string, mixed> */
    public function retrieve(string $sessionId): array
    {
        $apiKey = trim((string) config('services.didit.api_key'));
        if ($apiKey === '') {
            throw new BackgroundCheckProviderException(
                'Didit API key is not configured.',
                'DIDIT_NOT_CONFIGURED',
            );
        }

        $endpoint = rtrim((string) config('services.didit.base_url'), '/')
            .'/v3/session/'.rawurlencode($sessionId).'/decision/';

        $response = Http::acceptJson()
            ->withHeader('X-Api-Key', $apiKey)
            ->timeout(20)
            ->get($endpoint);

        if ($response->failed()) {
            throw new BackgroundCheckProviderException(
                'Didit decision refresh failed with HTTP '.$response->status().'.',
                'DIDIT_HTTP_'.$response->status(),
                $response->status() === 429 || $response->serverError(),
            );
        }

        $decision = $response->json();
        if (! is_array($decision)) {
            throw new BackgroundCheckProviderException(
                'Didit returned a non-JSON decision.',
                'DIDIT_INVALID_RESPONSE',
            );
        }

        return $decision;
    }
}
