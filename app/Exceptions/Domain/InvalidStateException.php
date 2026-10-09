<?php

namespace App\Exceptions\Domain;

class InvalidStateException extends DomainException
{
    public function errorCode(): string
    {
        return 'INVALID_STATE';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
