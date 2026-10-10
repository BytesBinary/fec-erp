<?php

namespace App\Services\ResultPortal;

use App\Enums\PortalExamKind;

/**
 * One entry of the portal's exam drop-down, with the semester, exam year and
 * session tag read from its free-text title.
 */
final readonly class ExamListing
{
    public function __construct(
        public int $id,
        public string $title,
        public PortalExamKind $kind,
        public ?int $semester,
        public ?int $examYear = null,
        public ?string $sessionTag = null,
    ) {}
}
