<?php

namespace App\DTOs\BackgroundChecks;

final readonly class ProviderResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public ?string $reference,
        public string $providerStatus,
        public string $classification,
        public array $raw,
    ) {}

    public function isPending(): bool
    {
        return $this->classification === 'pending';
    }
}
