<?php

namespace App\Exceptions\Domain;

class ConflictException extends DomainException
{
    public function errorCode(): string
    {
        return 'CONFLICT';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
