<?php

namespace App\Services\Verification;

use App\Models\UserVerification;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DiditVerificationImageStorage
{
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /** @var array<string, string> */
    private const CONTENT_TYPE_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/tiff' => 'tiff',
    ];

    /**
     * Download every available Didit verification image.
     *
     * @return list<string> Database fields whose downloads failed
     */
    public function storeAvailableImages(UserVerification $verification): array
    {
        $payload = $verification->didit_payload ?? [];
        $decision = is_array($payload['decision'] ?? null) ? $payload['decision'] : $payload;
        $sources = $this->imageSources($decision);
        $updates = [];
        $failures = [];

        foreach ($sources as $field => $url) {
            if ($this->alreadyStored($verification, $field)) {
                continue;
            }

            try {
                $updates[$field] = $this->download($verification, $field, $url);
            } catch (Throwable $exception) {
                $failures[] = $field;

                Log::warning('Didit verification image could not be stored', [
                    'verification_id' => $verification->id,
                    'session_id' => $verification->didit_session_id,
                    'field' => $field,
                    'source_host' => parse_url($url, PHP_URL_HOST),
                    'error' => $this->safeErrorMessage($exception),
                ]);
            }
        }

        // Only successful downloads are included, so missing or failed images
        // can never clear evidence that is already stored on the verification.
        if ($updates !== []) {
            $verification->forceFill($updates)->save();
        }

        return $failures;
    }

    /** @return array<string, string> */
    private function imageSources(array $decision): array
    {
        $idVerification = $this->preferredResult(
            $decision['id_verifications'] ?? [],
            ['front_image', 'full_front_image', 'back_image', 'full_back_image'],
        );
        $liveness = $this->preferredResult($decision['liveness_checks'] ?? [], ['reference_image']);
        $faceMatch = $this->preferredResult($decision['face_matches'] ?? [], ['target_image']);

        return array_filter([
            'face_id_image' => $this->firstUrl($liveness, ['reference_image'])
                ?? $this->firstUrl($faceMatch, ['target_image']),
            'national_id_front_image' => $this->firstUrl($idVerification, ['front_image', 'full_front_image']),
            'national_id_back_image' => $this->firstUrl($idVerification, ['back_image', 'full_back_image']),
        ], fn (mixed $url): bool => is_string($url) && trim($url) !== '');
    }

    /** @param list<string> $imageKeys */
    private function preferredResult(mixed $results, array $imageKeys): array
    {
        if (! is_array($results)) {
            return [];
        }

        $items = array_values(array_filter($results, function (mixed $result) use ($imageKeys): bool {
            return is_array($result) && $this->firstUrl($result, $imageKeys) !== null;
        }));

        foreach ($items as $item) {
            if (($item['status'] ?? null) === 'Approved') {
                return $item;
            }
        }

        return $items[0] ?? [];
    }

    /** @param list<string> $keys */
    private function firstUrl(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            $url = $item[$key] ?? null;
            if (is_string($url) && trim($url) !== '') {
                return trim($url);
            }
        }

        return null;
    }

    private function alreadyStored(UserVerification $verification, string $field): bool
    {
        $path = $verification->{$field};
        if (! is_string($path) || ! str_contains($path, '/didit/'.$this->storageSession($verification).'/')) {
            return false;
        }

        return Storage::disk('public')->exists($path);
    }

    private function download(UserVerification $verification, string $field, string $url): string
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new \RuntimeException('Didit image URL is not a valid HTTPS URL.');
        }

        $response = Http::accept('image/*')
            ->connectTimeout(5)
            ->timeout(15)
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException('Didit image download returned HTTP '.$response->status().'.');
        }

        $contents = $response->body();
        if ($contents === '') {
            throw new \RuntimeException('Didit image download returned an empty body.');
        }
        if (strlen($contents) > self::MAX_IMAGE_BYTES) {
            throw new \RuntimeException('Didit image download exceeded the allowed size.');
        }

        $extension = $this->extension($response);
        $path = $this->path($verification, $field, $extension);

        if (! Storage::disk('public')->put($path, $contents)) {
            throw new \RuntimeException('The public storage disk rejected the Didit image.');
        }

        return $path;
    }

    private function extension(Response $response): string
    {
        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = self::CONTENT_TYPE_EXTENSIONS[$contentType] ?? null;

        if (! $extension) {
            throw new \RuntimeException('Didit image returned an unsupported content type.');
        }

        return $extension;
    }

    private function path(UserVerification $verification, string $field, string $extension): string
    {
        $directory = $field === 'face_id_image'
            ? 'verification/face-id'
            : 'verification/national-id';

        $name = match ($field) {
            'face_id_image' => 'selfie',
            'national_id_front_image' => 'front',
            'national_id_back_image' => 'back',
        };

        return sprintf(
            '%s/didit/%s/%s.%s',
            $directory,
            $this->storageSession($verification),
            $name,
            $extension,
        );
    }

    private function storageSession(UserVerification $verification): string
    {
        $session = $verification->didit_session_id ?: 'verification-'.$verification->id;

        return preg_replace('/[^A-Za-z0-9_-]/', '-', $session) ?: 'verification-'.$verification->id;
    }

    private function safeErrorMessage(Throwable $exception): string
    {
        return preg_replace(
            '/https?:\/\/\S+/i',
            '[redacted-url]',
            $exception->getMessage(),
        ) ?: 'Didit image storage failed.';
    }
}
