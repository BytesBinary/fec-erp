<?php

namespace App\Exceptions\Domain;

class RateLimitedException extends DomainException
{
    public function errorCode(): string
    {
        return 'RATE_LIMITED';
    }

    public function httpStatus(): int
    {
        return 429;
    }
}
