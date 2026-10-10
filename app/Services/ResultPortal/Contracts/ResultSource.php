<?php

namespace App\Services\ResultPortal\Contracts;

use App\Exceptions\Domain\ResultPortalException;
use App\Models\Student;
use App\Services\ResultPortal\PortalFetch;

/**
 * Where official results come from. The university portal is one adapter; an
 * official data feed can replace it without touching the rest.
 */
interface ResultSource
{
    /**
     * @throws ResultPortalException
     */
    public function fetch(Student $student, ?int $onlyExamId = null): PortalFetch;
}
