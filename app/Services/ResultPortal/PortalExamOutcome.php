<?php

namespace App\Services\ResultPortal;

/**
 * One exam's answer for one student: the parsed page plus the raw HTML it came from.
 */
final readonly class PortalExamOutcome
{
    public function __construct(public ExamListing $exam, public PortalPage $page, public string $rawHtml) {}
}
