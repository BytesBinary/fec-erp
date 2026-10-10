<?php

namespace App\Services\ResultPortal;

/**
 * One parsed course grade of one portal exam.
 */
final readonly class PortalResultRow
{
    public function __construct(
        public ExamListing $exam,
        public string $courseCode,
        public ?string $courseTitle,
        public ?float $credits,
        public ?string $letter,
        public ?float $gradePoint,
    ) {}
}
