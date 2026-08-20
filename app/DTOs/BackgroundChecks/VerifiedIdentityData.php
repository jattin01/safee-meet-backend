<?php

namespace App\DTOs\BackgroundChecks;

use Carbon\CarbonImmutable;

final readonly class VerifiedIdentityData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public CarbonImmutable $dateOfBirth,
        public string $city,
        public string $state,
        public string $postalCode,
        public string $country,
    ) {}

    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            mb_strtolower($this->firstName),
            mb_strtolower($this->lastName),
            $this->dateOfBirth->format('Y-m-d'),
            mb_strtoupper($this->city),
            mb_strtoupper($this->state),
            mb_strtoupper($this->country),
        ]));
    }

    /** @return array<string, string> */
    public function toProviderPayload(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'date_of_birth' => $this->dateOfBirth->format('Y-m-d'),
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postalCode,
            'country' => 'US',
        ];
    }
}
