<?php

namespace App\Services\ResultPortal;

use App\Exceptions\Domain\ResultPortalException;
use App\Models\PortalExam;
use App\Models\Student;
use App\Services\ResultPortal\Contracts\ResultSource;
use Illuminate\Support\Facades\Cache;

/**
 * Looks a student up on the portal: program from the department, session from
 * the batch, then only the exams inside the student's admission-year window,
 * taken from the saved exam list (live list only when nothing is saved yet).
 */
class DuPortalResultSource implements ResultSource
{
    public function __construct(
        protected PortalClient $client,
        protected ExamCatalog $catalog,
        protected DuResultParser $parser,
    ) {}

    public function fetch(Student $student, ?int $onlyExamId = null): PortalFetch
    {
        $student->loadMissing(['department', 'batch']);

        $programId = config('result_portal.department_programs')[$student->department?->code] ?? throw new ResultPortalException('The department is not mapped to a portal program.');
        $sessionId = $this->sessionId((string) $student->batch?->session);

        $exams = $this->catalog->applicable($this->listings($programId), $student);

        if ($onlyExamId !== null) {
            $exams = array_values(array_filter($exams, fn (ExamListing $exam): bool => $exam->id === $onlyExamId));
        }

        $rows = [];
        $outcomes = [];
        $notVerified = 0;
        $delayMicroseconds = (int) config('result_portal.request_delay_ms') * 1000;

        foreach ($exams as $index => $exam) {
            if ($index > 0 && $delayMicroseconds > 0) {
                usleep($delayMicroseconds);
            }

            $html = $this->client->lookupHtml((string) $student->registration_number, $programId, $sessionId, $exam->id);
            $page = $this->parser->parse($html);

            if ($page->status === PortalPageStatus::NotVerified) {
                $notVerified++;

                continue;
            }

            if ($page->registration() !== trim((string) $student->registration_number)) {
                throw new ResultPortalException("The portal returned a result for another registration number ({$page->registration()}).");
            }

            $outcomes[] = new PortalExamOutcome($exam, $page, $html);

            foreach ($page->subjects as $subject) {
                $rows[] = new PortalResultRow($exam, $subject['code'], $subject['title'], null, $subject['letter'], $subject['point']);
            }
        }

        return new PortalFetch($rows, count($exams), $outcomes, $notVerified);
    }

    /**
     * One lookup of one student in one exam: found or "not verified". Used
     * to confirm that an exam's results are visible.
     *
     * @throws ResultPortalException
     */
    public function probe(Student $student, int $examId): PortalPage
    {
        $student->loadMissing(['department', 'batch']);

        $programId = config('result_portal.department_programs')[$student->department?->code] ?? throw new ResultPortalException('The department is not mapped to a portal program.');
        $page = $this->parser->parse($this->client->lookupHtml((string) $student->registration_number, $programId, $this->sessionId((string) $student->batch?->session), $examId));

        if ($page->status === PortalPageStatus::Found && $page->registration() !== trim((string) $student->registration_number)) {
            throw new ResultPortalException("The portal returned a result for another registration number ({$page->registration()}).");
        }

        return $page;
    }

    /**
     * @return list<ExamListing>
     */
    protected function listings(int $programId): array
    {
        $saved = PortalExam::query()->where('program_id', $programId)->get();

        if ($saved->isNotEmpty()) {
            return $saved->map(fn (PortalExam $exam): ExamListing => new ExamListing($exam->portal_exam_id, $exam->title, $exam->kind, $exam->semester, $exam->exam_year, $exam->session_tag))->all();
        }

        return $this->catalog->parse($this->client->examOptionsHtml($programId));
    }

    protected function sessionId(string $session): int
    {
        $sessions = Cache::remember('result_portal.sessions', now()->addDay(), function (): array {
            preg_match_all('/<option value="(\d+)">(\d{4}-\d{4})<\/option>/', $this->client->sessionsHtml(), $matches, PREG_SET_ORDER);

            return collect($matches)->mapWithKeys(fn (array $match): array => [$match[2] => (int) $match[1]])->all();
        });

        return $sessions[trim($session)] ?? throw new ResultPortalException("The batch session \"{$session}\" is not on the portal's session list.");
    }
}
