<?php

namespace App\Exceptions\Domain;

class NotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'NOT_FOUND';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
