<?php

namespace App\Services\Library;

use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\ValidationException;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Authorization\Authorizer;
use App\Support\Authorization\ResourceScope;
use Illuminate\Support\Collection;

/**
 * Minimal library module needed by clearance: loans and fines.
 */
class LibraryService
{
    public function __construct(protected Authorizer $authorizer, protected AuditLogger $audit) {}

    /**
     * @return Collection<int, LibraryLoan>
     */
    public function loansOf(User $actor, Student $student, bool $outstandingOnly = false): Collection
    {
        $this->authorizeAccess($actor, $student, 'library_loans:view');

        return LibraryLoan::query()->where('student_id', $student->getKey())->when($outstandingOnly, fn ($query) => $query->outstanding())->orderBy('id')->get();
    }

    /**
     * Unreturned books and unpaid fines of a student.
     *
     * @return array{outstanding_loans: int, unpaid_fines: float, items: Collection<int, LibraryLoan>}
     */
    public function duesOf(User $actor, Student $student): array
    {
        $this->authorizeAccess($actor, $student, 'library_dues:view');

        $loans = LibraryLoan::query()->where('student_id', $student->getKey())->where(fn ($query) => $query->whereNull('returned_on')->orWhere(fn ($fine) => $fine->where('fine_amount', '>', 0)->whereNull('fine_settled_at')))->get();

        return [
            'outstanding_loans' => $loans->whereNull('returned_on')->count(),
            'unpaid_fines' => (float) $loans->filter(fn (LibraryLoan $loan): bool => $loan->hasUnpaidFine())->sum('fine_amount'),
            'items' => $loans,
        ];
    }

    public function issue(User $actor, Student $student, string $bookTitle, \DateTimeInterface|string $dueOn, ?string $accessionNo = null): LibraryLoan
    {
        $this->authorizer->authorize($actor, 'library_loans:manage');

        if (trim($bookTitle) === '') {
            throw new ValidationException('A book title is required.');
        }

        return $this->audit->as($actor, fn (): LibraryLoan => LibraryLoan::query()->create([
            'student_id' => $student->getKey(),
            'book_title' => $bookTitle,
            'accession_no' => $accessionNo,
            'issued_on' => today(),
            'due_on' => $dueOn,
        ]));
    }

    public function markReturned(User $actor, LibraryLoan $loan, float $fine = 0.0): LibraryLoan
    {
        $this->authorizer->authorize($actor, 'library_loans:manage');

        if ($loan->returned_on !== null) {
            throw new InvalidStateException('This book was already returned.');
        }

        $this->audit->as($actor, fn () => $loan->update(['returned_on' => today(), 'fine_amount' => max(0, $fine)]));

        return $loan;
    }

    public function settleFine(User $actor, LibraryLoan $loan): LibraryLoan
    {
        $this->authorizer->authorize($actor, 'library_loans:manage');

        $this->audit->as($actor, fn () => $loan->update(['fine_settled_at' => now()]));

        return $loan;
    }

    protected function authorizeAccess(User $actor, Student $student, string $permission): void
    {
        if ($student->user_id === $actor->getKey()) {
            $this->authorizer->authorize($actor, 'clearance:view', ResourceScope::forOwner($actor->getKey()));

            return;
        }

        $this->authorizer->authorize($actor, $permission);
    }
}
