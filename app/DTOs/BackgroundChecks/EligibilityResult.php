<?php

namespace App\DTOs\BackgroundChecks;

use App\Models\BackgroundCheck;
use App\Models\Subscription;
use App\Models\UserConsent;
use App\Models\UserVerification;

final readonly class EligibilityResult
{
    public function __construct(
        public bool $eligible,
        public string $reason,
        public ?Subscription $subscription = null,
        public ?UserVerification $verification = null,
        public ?UserConsent $consent = null,
        public ?VerifiedIdentityData $identity = null,
        public ?BackgroundCheck $existingCheck = null,
        /** @var list<string> */
        public array $missingFields = [],
    ) {}
}
