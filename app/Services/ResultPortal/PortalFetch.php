<?php

namespace App\Services\ResultPortal;

/**
 * Everything a source returned for one student.
 */
final readonly class PortalFetch
{
    /**
     * @param  list<PortalResultRow>  $rows
     * @param  list<PortalExamOutcome>  $outcomes  exams where the portal had a result
     * @param  int  $notVerified  exams answered with the "not verified" message
     */
    public function __construct(public array $rows, public int $examsChecked, public array $outcomes = [], public int $notVerified = 0) {}
}
