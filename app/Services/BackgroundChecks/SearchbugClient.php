<?php

namespace App\Services\BackgroundChecks;

use App\Contracts\CriminalBackgroundCheckProvider;
use App\DTOs\BackgroundChecks\ProviderResult;
use App\DTOs\BackgroundChecks\VerifiedIdentityData;
use App\Exceptions\BackgroundCheckProviderException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SearchbugClient implements CriminalBackgroundCheckProvider
{
    public function submit(VerifiedIdentityData $identity, string $idempotencyKey): ProviderResult
    {
        $payload = [
            'CO_CODE' => $this->requiredConfig('co_code'),
            'PASS' => $this->requiredConfig('pass'),
            'TYPE' => (string) config('services.searchbug.type', 'api_crm'),
            'FNAME' => $identity->firstName,
            'LNAME' => $identity->lastName,
            'CITY' => $identity->city,
            'STATE' => $identity->state,
            'DOB' => $identity->dateOfBirth->format('m/d/Y'),
            'FORMAT' => 'JSON',
            'REF' => mb_substr($idempotencyKey, 0, 100),
        ];

        $response = Http::acceptJson()
            ->asMultipart()
            ->timeout((int) config('services.searchbug.timeout', 20))
            ->post($this->requiredConfig('endpoint'), $payload);

        return $this->parse($response, $idempotencyKey);
    }

    public function retrieve(string $providerReference): ProviderResult
    {
        throw new BackgroundCheckProviderException(
            'Searchbug Criminal Records API returns a synchronous result.',
            'POLLING_NOT_SUPPORTED',
        );
    }

    private function parse(Response $response, string $idempotencyKey): ProviderResult
    {
        if ($response->failed()) {
            throw new BackgroundCheckProviderException(
                message: 'Searchbug request failed with HTTP '.$response->status().'.',
                providerCode: 'HTTP_'.$response->status(),
                retryable: $response->status() === 429 || $response->serverError(),
            );
        }

        if ($response->status() === 204 || trim($response->body()) === '') {
            return $this->verifiedWithoutResult($idempotencyKey, []);
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new BackgroundCheckProviderException(
                'Searchbug returned a non-JSON response.',
                'INVALID_RESPONSE',
            );
        }

        if ($this->hasErrors($data['Error'] ?? $data['error'] ?? null)) {
            throw new BackgroundCheckProviderException(
                'Searchbug rejected the criminal-record request.',
                'SEARCHBUG_REQUEST_REJECTED',
            );
        }

        $envelopeStatus = $data['Status'] ?? $data['status'] ?? null;
        if ($envelopeStatus !== null) {
            $status = mb_strtoupper(trim((string) $envelopeStatus));
            if ($this->isFailureStatus($status)) {
                throw new BackgroundCheckProviderException(
                    'Searchbug returned an explicit verification failure.',
                    'SEARCHBUG_VERIFICATION_FAILED',
                );
            }

            if ($status === 'NORESULTS') {
                return new ProviderResult(
                    reference: $this->reference($idempotencyKey),
                    providerStatus: $status,
                    classification: 'clear',
                    raw: $data,
                );
            }

            $recordStatuses = array_map(
                fn (string $value): string => mb_strtoupper(trim($value)),
                (array) config('services.searchbug.record_statuses', ['RESULTS']),
            );
            if (in_array($status, $recordStatuses, true)
                && filled($data['Data'] ?? $data['data'] ?? null)) {
                return new ProviderResult(
                    reference: $this->reference($idempotencyKey),
                    providerStatus: $status,
                    classification: 'flagged',
                    raw: $data,
                );
            }

            return $this->verifiedWithoutResult($idempotencyKey, $data, $status);
        }

        // Retain support for the response shape shown in Searchbug's public
        // documentation while preferring the actual account response above.
        $result = data_get($data, 'result', $data);
        if (! is_array($result)) {
            throw new BackgroundCheckProviderException(
                'Searchbug returned an invalid result object.',
                'INVALID_RESPONSE',
            );
        }

        if ($this->hasErrors(data_get($result, 'meta.errors'))) {
            throw new BackgroundCheckProviderException(
                'Searchbug rejected the criminal-record request.',
                'SEARCHBUG_REQUEST_REJECTED',
            );
        }

        $rows = data_get($result, 'meta.rows');
        if (! is_numeric($rows)) {
            return $this->verifiedWithoutResult($idempotencyKey, $data);
        }

        $hasRecords = (int) $rows > 0;

        return new ProviderResult(
            reference: $this->reference($idempotencyKey),
            providerStatus: $hasRecords ? 'records_found' : 'no_records_found',
            classification: $hasRecords ? 'flagged' : 'clear',
            raw: $data,
        );
    }

    private function reference(string $idempotencyKey): string
    {
        return 'searchbug:'.mb_substr($idempotencyKey, 0, 54);
    }

    /** @param array<string, mixed> $raw */
    private function verifiedWithoutResult(
        string $idempotencyKey,
        array $raw,
        string $providerStatus = 'NO_STATUS',
    ): ProviderResult {
        return new ProviderResult(
            reference: $this->reference($idempotencyKey),
            providerStatus: $providerStatus !== '' ? $providerStatus : 'NO_STATUS',
            classification: 'verified',
            raw: $raw,
        );
    }

    private function isFailureStatus(string $status): bool
    {
        $failureStatuses = array_map(
            fn (string $value): string => mb_strtoupper(trim($value)),
            (array) config('services.searchbug.failure_statuses', []),
        );

        return in_array($status, $failureStatuses, true);
    }

    private function hasErrors(mixed $errors): bool
    {
        if ($errors === null || $errors === '' || $errors === [] || $errors === false) {
            return false;
        }

        if (is_array($errors)) {
            return collect($errors)->flatten()->contains(
                fn (mixed $error): bool => filled($error),
            );
        }

        return true;
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config("services.searchbug.{$key}", ''));
        if ($value === '') {
            throw new BackgroundCheckProviderException(
                "Searchbug {$key} is not configured.",
                'PROVIDER_NOT_CONFIGURED',
            );
        }

        return $value;
    }
}
