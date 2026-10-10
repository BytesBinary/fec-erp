<?php

namespace Tests\Support;

use App\Exceptions\Domain\ResultPortalException;
use App\Models\Student;
use App\Services\ResultPortal\Contracts\ResultSource;
use App\Services\ResultPortal\PortalFetch;
use App\Services\ResultPortal\PortalResultRow;

/**
 * Scriptable stand-in for the exam portal: tests queue what each pull returns.
 */
class FakeResultSource implements ResultSource
{
    /** @var list<PortalFetch|ResultPortalException> */
    public array $script = [];

    /** @var list<int> */
    public array $fetchedStudentIds = [];

    /**
     * @param  list<PortalResultRow>  $rows
     */
    public function willReturn(array $rows, int $examsChecked = 1): self
    {
        $this->script[] = new PortalFetch($rows, $examsChecked);

        return $this;
    }

    public function willFail(ResultPortalException $exception): self
    {
        $this->script[] = $exception;

        return $this;
    }

    public function fetch(Student $student, ?int $onlyExamId = null): PortalFetch
    {
        $this->fetchedStudentIds[] = $student->getKey();

        $next = array_shift($this->script) ?? new PortalFetch([], 0);

        if ($next instanceof ResultPortalException) {
            throw $next;
        }

        return $next;
    }
}
