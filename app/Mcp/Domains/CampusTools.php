<?php

namespace App\Mcp\Domains;

use App\Exceptions\Domain\NotFoundException;
use App\Mcp\Registry\Param;
use App\Mcp\Registry\ToolDefinition;
use App\Models\Hall;
use App\Models\HallDue;
use App\Models\HallResidency;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Models\User;
use App\Services\Halls\HallResidencyService;
use App\Services\Library\LibraryService;

/**
 * Minimal halls and library needed by clearance (spec §4.2).
 */
final class CampusTools
{
    /**
     * @return list<ToolDefinition>
     */
    public static function tools(): array
    {
        $student = fn (int $id): Student => Student::query()->find($id) ?? throw new NotFoundException(__('erp.errors.not_found', ['entity' => 'student']));

        return [
            ToolDefinition::make('hall_assign_student', 'Assign student to hall', 'Move a student into a hall (ends their previous residency). A provost can only use their own hall.')
                ->permission('hall:assign_student')
                ->params(Param::integer('student_id', 'The student id.', required: true), Param::integer('hall_id', 'The hall id.', required: true), Param::string('room', 'Room number.'))
                ->write(idempotent: true)
                ->covers(HallResidencyService::class.'::assign')
                ->handler(function (User $actor, array $args) use ($student): array {
                    $residency = app(HallResidencyService::class)->assign($actor, $student((int) $args['student_id']), Hall::query()->findOrFail($args['hall_id']), $args['room'] ?? null);

                    return ['summary' => 'Student assigned to the hall.', 'data' => ToolSupport::item($residency), 'entity' => ['HallResidency', $residency->id]];
                }),

            ToolDefinition::make('hall_vacate_student', 'End hall residency', 'End a residency (the student leaves the hall). Needs confirm=true.')
                ->permission('hall:assign_student')
                ->params(Param::integer('residency_id', 'The residency id (from hall_residents_list).', required: true))
                ->destructive(function (User $actor, array $args): array {
                    $residency = HallResidency::query()->with(['student.user', 'hall'])->findOrFail($args['residency_id']);

                    return ['student' => $residency->student->user->name, 'hall' => $residency->hall->name, 'effect' => 'The residency ends today.'];
                })
                ->covers(HallResidencyService::class.'::vacate')
                ->handler(function (User $actor, array $args): array {
                    $residency = app(HallResidencyService::class)->vacate($actor, HallResidency::query()->findOrFail($args['residency_id']));

                    return ['summary' => 'Residency ended.', 'data' => ToolSupport::item($residency), 'entity' => ['HallResidency', $residency->id]];
                }),

            ToolDefinition::make('hall_residents_list', 'Hall residents', 'List the students currently living in the halls you manage (provosts: their own hall), with room numbers. Returns items and next_cursor.')
                ->permission('hall:assign_student')
                ->params(...ToolSupport::paging())
                ->covers(HallResidencyService::class.'::residents', HallResidencyService::class.'::currentFor')
                ->handler(function (User $actor, array $args): array {
                    $page = ToolSupport::paginateQuery(app(HallResidencyService::class)->residents($actor), $args);

                    return ['summary' => count($page['items']).' resident(s).', 'data' => $page];
                }),

            ToolDefinition::make('hall_dues_get', 'Hall dues', 'List the hall dues of a student (open and settled), used when deciding a clearance.')
                ->permission('hall_dues:view')
                ->params(Param::integer('student_id', 'The student id.', required: true), Param::boolean('open_only', 'Only unsettled dues.'))
                ->covers(HallResidencyService::class.'::duesOf')
                ->handler(function (User $actor, array $args) use ($student): array {
                    $dues = app(HallResidencyService::class)->duesOf($actor, $student((int) $args['student_id']), (bool) ($args['open_only'] ?? false));

                    return ['summary' => $dues->count().' due(s), '.$dues->whereNull('settled_at')->count().' open.', 'data' => ToolSupport::items($dues)];
                }),

            ToolDefinition::make('hall_due_record', 'Record hall due', 'Record an amount a student owes the hall (the student must live in the provost\'s hall).')
                ->permission('hall_dues:manage')
                ->params(Param::integer('student_id', 'The student id.', required: true), Param::string('description', 'What the due is for.', true), Param::number('amount', 'Amount, positive.', true))
                ->creates()
                ->covers(HallResidencyService::class.'::recordDue')
                ->handler(function (User $actor, array $args) use ($student): array {
                    $due = app(HallResidencyService::class)->recordDue($actor, $student((int) $args['student_id']), $args['description'], (float) $args['amount']);

                    return ['summary' => 'Due recorded.', 'data' => ToolSupport::item($due), 'entity' => ['HallDue', $due->id]];
                }),

            ToolDefinition::make('hall_due_settle', 'Settle hall due', 'Mark a hall due as paid after the student settled it; it then no longer blocks the hall clearance decision. Get due ids from hall_dues_get.')
                ->permission('hall_dues:manage')
                ->params(Param::integer('due_id', 'The due id.', required: true))
                ->write(idempotent: true)
                ->covers(HallResidencyService::class.'::settleDue')
                ->handler(function (User $actor, array $args): array {
                    $due = app(HallResidencyService::class)->settleDue($actor, HallDue::query()->findOrFail($args['due_id']));

                    return ['summary' => 'Due settled.', 'data' => ToolSupport::item($due), 'entity' => ['HallDue', $due->id]];
                }),

            ToolDefinition::make('library_loans_list_for_student', 'Library loans', 'List a student\'s library loans (borrowed books, due and return dates, fines). Set outstanding_only to see only unreturned books.')
                ->permission('library_loans:view')
                ->params(Param::integer('student_id', 'The student id.', required: true), Param::boolean('outstanding_only', 'Only books not returned.'))
                ->covers(LibraryService::class.'::loansOf')
                ->handler(function (User $actor, array $args) use ($student): array {
                    $loans = app(LibraryService::class)->loansOf($actor, $student((int) $args['student_id']), (bool) ($args['outstanding_only'] ?? false));

                    return ['summary' => $loans->count().' loan(s).', 'data' => ToolSupport::items($loans)];
                }),

            ToolDefinition::make('library_dues_get', 'Library dues', 'Get a student\'s unreturned books and unpaid fines (what a librarian checks before approving clearance).')
                ->permission('library_dues:view')
                ->params(Param::integer('student_id', 'The student id.', required: true))
                ->covers(LibraryService::class.'::duesOf')
                ->handler(function (User $actor, array $args) use ($student): array {
                    $dues = app(LibraryService::class)->duesOf($actor, $student((int) $args['student_id']));

                    return ['summary' => "{$dues['outstanding_loans']} book(s) outstanding, {$dues['unpaid_fines']} unpaid fines.", 'data' => ['outstanding_loans' => $dues['outstanding_loans'], 'unpaid_fines' => $dues['unpaid_fines'], 'items' => ToolSupport::items($dues['items'])]];
                }),

            ToolDefinition::make('library_loan_issue', 'Issue a book', 'Record that a book was issued to a student with its due date. Pass an idempotencyKey when retrying so the loan is not recorded twice.')
                ->permission('library_loans:manage')
                ->params(Param::integer('student_id', 'The student id.', required: true), Param::string('book_title', 'Book title.', true), Param::string('due_on', 'Due date, YYYY-MM-DD.', true), Param::string('accession_no', 'Accession number.'))
                ->creates()
                ->covers(LibraryService::class.'::issue')
                ->handler(function (User $actor, array $args) use ($student): array {
                    $loan = app(LibraryService::class)->issue($actor, $student((int) $args['student_id']), $args['book_title'], $args['due_on'], $args['accession_no'] ?? null);

                    return ['summary' => 'Book issued.', 'data' => ToolSupport::item($loan), 'entity' => ['LibraryLoan', $loan->id]];
                }),

            ToolDefinition::make('library_loan_return', 'Return a book', 'Mark a library loan as returned today, optionally recording a late fine that must then be settled with library_fine_settle.')
                ->permission('library_loans:manage')
                ->params(Param::integer('loan_id', 'The loan id.', required: true), Param::number('fine', 'Fine amount, if any.'))
                ->write()
                ->covers(LibraryService::class.'::markReturned')
                ->handler(function (User $actor, array $args): array {
                    $loan = app(LibraryService::class)->markReturned($actor, LibraryLoan::query()->findOrFail($args['loan_id']), (float) ($args['fine'] ?? 0));

                    return ['summary' => 'Book returned.', 'data' => ToolSupport::item($loan), 'entity' => ['LibraryLoan', $loan->id]];
                }),

            ToolDefinition::make('library_fine_settle', 'Settle library fine', 'Mark a library fine as paid so it no longer blocks the library clearance decision. Get loan ids from library_dues_get.')
                ->permission('library_loans:manage')
                ->params(Param::integer('loan_id', 'The loan id.', required: true))
                ->write(idempotent: true)
                ->covers(LibraryService::class.'::settleFine')
                ->handler(function (User $actor, array $args): array {
                    $loan = app(LibraryService::class)->settleFine($actor, LibraryLoan::query()->findOrFail($args['loan_id']));

                    return ['summary' => 'Fine settled.', 'data' => ToolSupport::item($loan), 'entity' => ['LibraryLoan', $loan->id]];
                }),
        ];
    }
}
