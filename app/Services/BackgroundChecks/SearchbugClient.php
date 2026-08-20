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

        $data = $response->json();
        if (! is_array($data)) {
            throw new BackgroundCheckProviderException(
                'Searchbug returned a non-JSON response.',
                'INVALID_RESPONSE',
            );
        }

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
            throw new BackgroundCheckProviderException(
                'Searchbug response did not contain a result count.',
                'INVALID_RESPONSE',
            );
        }

        $hasRecords = (int) $rows > 0;

        return new ProviderResult(
            reference: 'searchbug:'.mb_substr($idempotencyKey, 0, 54),
            providerStatus: $hasRecords ? 'records_found' : 'no_records_found',
            classification: $hasRecords ? 'flagged' : 'clear',
            raw: $data,
        );
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
