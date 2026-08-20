<?php

namespace App\Services\BackgroundChecks;

use App\DTOs\BackgroundChecks\IdentityExtractionResult;
use App\DTOs\BackgroundChecks\VerifiedIdentityData;
use App\Models\UserVerification;
use Carbon\CarbonImmutable;
use Throwable;

class DiditVerifiedIdentityExtractor
{
    public function extract(UserVerification $verification): IdentityExtractionResult
    {
        if ($verification->provider !== 'didit'
            || $verification->status !== 'approved'
            || $verification->didit_decision_status !== 'Approved'
            || (int) $verification->verification_level < 1) {
            return new IdentityExtractionResult(null, 'LEVEL_ONE_NOT_APPROVED');
        }

        $payload = $verification->didit_payload ?? [];
        $decision = is_array($payload['decision'] ?? null) ? $payload['decision'] : $payload;
        $idVerifications = is_array($decision['id_verifications'] ?? null)
            ? $decision['id_verifications']
            : [];

        $approvedId = collect($idVerifications)->first(
            fn ($item): bool => is_array($item) && ($item['status'] ?? null) === 'Approved'
        );

        if (! is_array($approvedId)) {
            return new IdentityExtractionResult(null, 'DIDIT_ID_NOT_APPROVED');
        }

        $address = is_array($approvedId['parsed_address'] ?? null)
            ? $approvedId['parsed_address']
            : [];

        $values = [
            'first_name' => $this->string($approvedId['first_name'] ?? null),
            'last_name' => $this->string($approvedId['last_name'] ?? null),
            'date_of_birth' => $this->string($approvedId['date_of_birth'] ?? null),
            'city' => $this->string($address['city'] ?? null),
            'state' => $this->string($address['region'] ?? null),
            'postal_code' => $this->string($address['postal_code'] ?? null),
            'country' => $this->string($address['country'] ?? null),
        ];

        $required = collect($values)->except('postal_code')->all();
        $missing = array_keys(array_filter($required, fn (string $value): bool => $value === ''));

        if (($address['is_verified'] ?? false) !== true) {
            $missing[] = 'verified_address';
        }

        if ($missing !== []) {
            return new IdentityExtractionResult(
                null,
                'VERIFIED_DETAILS_INCOMPLETE',
                array_values(array_unique($missing)),
            );
        }

        if (! $this->isUnitedStates($values['country'])) {
            return new IdentityExtractionResult(null, 'COUNTRY_NOT_SUPPORTED');
        }

        try {
            $dateOfBirth = CarbonImmutable::parse($values['date_of_birth'])->startOfDay();
        } catch (Throwable) {
            return new IdentityExtractionResult(null, 'VERIFIED_DETAILS_INCOMPLETE', ['date_of_birth']);
        }

        if ($dateOfBirth->isFuture()) {
            return new IdentityExtractionResult(null, 'VERIFIED_DETAILS_INCOMPLETE', ['date_of_birth']);
        }

        return new IdentityExtractionResult(
            new VerifiedIdentityData(
                firstName: $values['first_name'],
                lastName: $values['last_name'],
                dateOfBirth: $dateOfBirth,
                city: $values['city'],
                state: mb_strtoupper($values['state']),
                postalCode: $values['postal_code'],
                country: 'US',
            ),
            'READY',
        );
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function isUnitedStates(string $country): bool
    {
        return in_array(mb_strtoupper(trim($country)), [
            'US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA',
        ], true);
    }
}
