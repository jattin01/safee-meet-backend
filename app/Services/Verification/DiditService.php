<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DiditService
{
    private string $apiKey;
    private string $baseUri;

    public function __construct()
    {
        $this->apiKey = config('services.didit.api_key');
        $this->baseUri = rtrim(config('services.didit.base_uri', 'https://verification.didit.me/v3/'), '/') . '/';
    }

    private function headers(array $extra = []): array
    {
        return array_merge([
            'x-api-key' => $this->apiKey,
        ], $extra);
    }

    public function submitIdVerification(string $frontPath, ?string $backPath = null, array $params = []): array
    {
        $disk = Storage::disk(config('filesystems.default'));
        $frontContents = $disk->get($frontPath);
        $frontName = basename($frontPath);

        $request = Http::withHeaders($this->headers())
            ->attach('front_image', $frontContents, $frontName);

        if ($backPath) {
            $backContents = $disk->get($backPath);
            $backName = basename($backPath);
            $request = $request->attach('back_image', $backContents, $backName);
        }

        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $request = $request->attach($key, (string) $value);
        }

        $response = $request->post($this->baseUri . 'id-verification/');

        return $this->decodeResponse($response);
    }

    public function submitPassiveLiveness(string $imagePath, array $params = []): array
    {
        $disk = Storage::disk(config('filesystems.default'));
        $contents = $disk->get($imagePath);
        $name = basename($imagePath);

        $request = Http::withHeaders($this->headers())
            ->attach('user_image', $contents, $name);

        foreach ($params as $key => $value) {
            $request = $request->attach($key, (string) $value);
        }

        $response = $request->post($this->baseUri . 'passive-liveness/');

        return $this->decodeResponse($response);
    }

    public function submitFaceMatch(string $userImagePath, string $refImagePath, array $params = []): array
    {
        $disk = Storage::disk(config('filesystems.default'));
        $userContents = $disk->get($userImagePath);
        $refContents = $disk->get($refImagePath);
        $userName = basename($userImagePath);
        $refName = basename($refImagePath);

        $request = Http::withHeaders($this->headers())
            ->attach('user_image', $userContents, $userName)
            ->attach('ref_image', $refContents, $refName);

        foreach ($params as $key => $value) {
            $request = $request->attach($key, (string) $value);
        }

        $response = $request->post($this->baseUri . 'face-match/');

        return $this->decodeResponse($response);
    }

    private function decodeResponse($response): array
    {
        if ($response->failed()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'body' => $response->body(),
            ];
        }

        return array_merge(['ok' => true, 'status' => $response->status()], $response->json() ?? []);
    }
}
