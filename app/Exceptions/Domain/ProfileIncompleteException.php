<?php

namespace App\Exceptions\Domain;

class ProfileIncompleteException extends DomainException
{
    public function errorCode(): string
    {
        return 'PROFILE_INCOMPLETE';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
