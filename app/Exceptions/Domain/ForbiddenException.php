<?php

namespace App\Exceptions\Domain;

class ForbiddenException extends DomainException
{
    public function errorCode(): string
    {
        return 'FORBIDDEN';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
