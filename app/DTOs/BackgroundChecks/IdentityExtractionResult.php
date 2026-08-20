<?php

namespace App\DTOs\BackgroundChecks;

final readonly class IdentityExtractionResult
{
    /** @param list<string> $missingFields */
    public function __construct(
        public ?VerifiedIdentityData $identity,
        public string $reason,
        public array $missingFields = [],
    ) {}

    public function isComplete(): bool
    {
        return $this->identity !== null;
    }
}
