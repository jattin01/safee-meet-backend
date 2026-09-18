<?php

namespace App\Services;

use App\Contracts\CriminalBackgroundCheckProvider;
use App\DTOs\BackgroundChecks\ProviderResult;
use App\DTOs\BackgroundChecks\VerifiedIdentityData;
use App\Exceptions\BackgroundCheckProviderException;
use Illuminate\Support\Facades\Http;

class SignzyCriminalSearchService implements CriminalBackgroundCheckProvider
{
    public function submit(VerifiedIdentityData $identity, string $idempotencyKey): ProviderResult
    {
        $result = $this->search([
            'first_name' => $identity->firstName,
            'last_name' => $identity->lastName,
            'dob' => $identity->dateOfBirth->format((string) config('services.signzy.dob_format', 'm/d/Y')),
            'person_city' => $identity->city,
            'person_state' => $identity->state,
        ]);

        return new ProviderResult(
            reference: 'signzy:'.mb_substr($idempotencyKey, 0, 57),
            providerStatus: $result['status'],
            classification: $result['status'] === 'CLEAR' ? 'clear' : 'flagged',
            raw: $result['raw_response'],
        );
    }

    public function retrieve(string $providerReference): ProviderResult
    {
        throw new BackgroundCheckProviderException(
            'Signzy Criminal Search returns a synchronous result.',
            'POLLING_NOT_SUPPORTED',
        );
    }

    public function search(array $data): array
    {
        $payload = [
            'businessName' => $data['business_name'] ?? '',
            'Ssn' => $data['ssn'] ?? '',
            'lastName' => $data['last_name'] ?? '',
            'firstName' => $data['first_name'] ?? '',
            'middleName' => $data['middle_name'] ?? '',
            'suffix' => $data['suffix'] ?? '',
            'addressLine1' => $data['address_line1'] ?? '',
            'addressLine2' => $data['address_line2'] ?? '',
            'dob' => $data['dob'] ?? '',
            'dobTo' => $data['dob_to'] ?? '',
            'offenseCity' => $data['offense_city'] ?? '',
            'offenseCounty' => $data['offense_county'] ?? '',
            'offenseState' => $data['offense_state'] ?? '',
            'personCity' => $data['person_city'] ?? '',
            'personState' => $data['person_state'] ?? '',
            'categoryTypes' => $data['category_types'] ?? '',
        ];

        if (empty($payload['firstName']) || empty($payload['lastName'])) {
            throw new BackgroundCheckProviderException('First name and last name are required for criminal search.', 'INVALID_REQUEST');
        }

        $baseUrl = rtrim($this->requiredConfig('base_url'), '/');
        $response = Http::acceptJson()
            ->asJson()
            ->withHeaders(['Authorization' => $this->requiredConfig('token')])
            ->timeout((int) config('services.signzy.timeout', 30))
            ->post("{$baseUrl}/api/v3/us/national-criminal-search", $payload);

        if ($response->failed()) {
            throw new BackgroundCheckProviderException(
                message: 'Signzy request failed with HTTP '.$response->status().'.',
                providerCode: 'HTTP_'.$response->status(),
                retryable: $response->status() === 429 || $response->serverError(),
            );
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new BackgroundCheckProviderException('Signzy returned a non-JSON response.', 'INVALID_RESPONSE');
        }

        return $this->normalizeResponse($data);
    }

    private function requiredConfig(string $key): string
    {
        $value = trim((string) config("services.signzy.{$key}", ''));
        if ($value === '') {
            throw new BackgroundCheckProviderException("Signzy {$key} is not configured.", 'PROVIDER_NOT_CONFIGURED');
        }

        return $value;
    }

    private function normalizeResponse(array $response): array
    {
        $records = data_get($response, 'result.crimRecords');
        $count = data_get($response, 'result.crimRecordCount');

        // Missing or malformed results must not become a successful clear check.
        if (! is_array($records) || ! array_is_list($records)
            || ! is_int($count) || $count < 0) {
            throw new BackgroundCheckProviderException('Signzy returned an invalid criminal-search result.', 'INVALID_RESPONSE');
        }

        return [
            'status' => $this->resolveStatus($records, $count),
            'record_count' => $count,
            'records' => $records,
            'reason' => data_get($response, 'reason'),
            'code' => data_get($response, 'code'),
            'raw_response' => $response,
        ];
    }

    private function resolveStatus(array $records, int $count): string
    {
        if ($count === 0 && $records === []) {
            return 'CLEAR';
        }

        // Candidates follow the existing potential-record/manual-review flow.
        return 'REVIEW';
    }
}
