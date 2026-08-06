<?php

namespace App\Support\Verification;

use App\Models\UserVerification;

class VerificationDocumentPresenter
{
    public static function make(UserVerification $verification): array
    {
        $payload = $verification->didit_payload ?? [];
        $decision = is_array($payload['decision'] ?? null) ? $payload['decision'] : $payload;
        $documents = [];

        foreach ((array) ($decision['id_verifications'] ?? []) as $index => $id) {
            if (! is_array($id)) {
                continue;
            }

            $type = $id['document_type'] ?? 'Identity document '.($index + 1);
            self::add($documents, "$type — Front", $id['front_image'] ?? null, $id['status'] ?? null);
            self::add($documents, "$type — Back", $id['back_image'] ?? null, $id['status'] ?? null);
            self::add($documents, "$type — Portrait", $id['portrait_image'] ?? null, $id['status'] ?? null);

            foreach ((array) ($id['extra_files'] ?? []) as $extraIndex => $file) {
                $url = is_array($file) ? ($file['url'] ?? $file['file_url'] ?? null) : $file;
                $label = is_array($file) ? ($file['name'] ?? $file['type'] ?? null) : null;
                self::add($documents, $label ?: 'Additional document '.($extraIndex + 1), $url, $id['status'] ?? null);
            }

            foreach ($id as $field => $value) {
                if (is_string($value) && preg_match('/(?:image|video|file)(?:_url)?$/', (string) $field)) {
                    self::add($documents, self::label((string) $field), $value, $id['status'] ?? null);
                }
            }
        }

        foreach ((array) ($decision['liveness_checks'] ?? []) as $index => $check) {
            if (! is_array($check)) {
                continue;
            }
            self::add($documents, $index ? 'Liveness image '.($index + 1) : 'Selfie / Liveness', $check['reference_image'] ?? null, $check['status'] ?? null);
            self::add($documents, $index ? 'Liveness video '.($index + 1) : 'Liveness video', $check['video_url'] ?? null, $check['status'] ?? null, 'video');
        }

        foreach ((array) ($decision['face_matches'] ?? []) as $index => $match) {
            if (! is_array($match)) {
                continue;
            }
            self::add($documents, $index ? 'Face match image '.($index + 1) : 'Face match image', $match['target_image'] ?? null, $match['status'] ?? null);
            self::add($documents, $index ? 'Face match source '.($index + 1) : 'Face match source', $match['source_image'] ?? null, $match['status'] ?? null);
        }

        // Older, manually uploaded evidence remains visible when no Didit URL exists.
        self::add($documents, 'Front ID', self::storageUrl($verification->national_id_front_image), $verification->status);
        self::add($documents, 'Back ID', self::storageUrl($verification->national_id_back_image), $verification->status);
        self::add($documents, 'Selfie / Liveness', self::storageUrl($verification->face_id_image), $verification->status);

        self::ensureExpectedDocument($documents, 'Front ID', fn (string $label) => str_contains(strtolower($label), 'front'));
        self::ensureExpectedDocument($documents, 'Back ID', fn (string $label) => str_contains(strtolower($label), 'back'));
        self::ensureExpectedDocument($documents, 'Selfie / Liveness', fn (string $label) => str_contains(strtolower($label), 'selfie') || str_contains(strtolower($label), 'liveness'));

        return [
            'overallStatus' => self::status($payload['status'] ?? $decision['status'] ?? $verification->didit_decision_status ?? $verification->status),
            'documents' => array_values($documents),
        ];
    }

    private static function add(array &$documents, string $label, mixed $url, mixed $status, ?string $type = null): void
    {
        if (! is_string($url) || trim($url) === '' || isset($documents[$url])) {
            return;
        }

        $path = strtolower(parse_url($url, PHP_URL_PATH) ?? '');
        $mediaType = str_ends_with($path, '.pdf') ? 'file' :
            (preg_match('/\.(mp4|mov|webm|avi)$/', $path) ? 'video' : 'image');

        $documents[$url] = [
            'label' => $label,
            'url' => $url,
            'type' => $type ?? $mediaType,
            'status' => self::status($status),
        ];
    }

    private static function storageUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }

    private static function label(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    private static function ensureExpectedDocument(array &$documents, string $label, callable $matches): void
    {
        foreach ($documents as $document) {
            if ($matches($document['label'])) {
                return;
            }
        }

        $documents['unavailable:'.$label] = [
            'label' => $label,
            'url' => null,
            'type' => 'image',
            'status' => 'Pending',
        ];
    }

    private static function status(mixed $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'approved', 'approve', 'verified', 'completed' => 'Verified',
            'declined', 'rejected', 'reject', 'failed' => 'Rejected',
            'expired', 'abandoned' => 'Expired',
            'in review', 'under review', 'review' => 'Under Review',
            default => 'Pending',
        };
    }
}
