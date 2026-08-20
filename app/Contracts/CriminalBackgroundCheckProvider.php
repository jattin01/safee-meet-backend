<?php

namespace App\Contracts;

use App\DTOs\BackgroundChecks\ProviderResult;
use App\DTOs\BackgroundChecks\VerifiedIdentityData;

interface CriminalBackgroundCheckProvider
{
    public function submit(VerifiedIdentityData $identity, string $idempotencyKey): ProviderResult;

    public function retrieve(string $providerReference): ProviderResult;
}
