<?php

namespace App\Exceptions\Domain;

class ValidationException extends DomainException
{
    public function errorCode(): string
    {
        return 'VALIDATION_ERROR';
    }

    public function httpStatus(): int
    {
        return 422;
    }
}
