<?php

namespace App\Exceptions;

use RuntimeException;

class BackgroundCheckProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerCode = 'PROVIDER_ERROR',
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }
}
