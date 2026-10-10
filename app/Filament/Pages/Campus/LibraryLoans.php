<?php

namespace App\Filament\Pages\Campus;

use App\Exceptions\Domain\DomainException;
use App\Models\LibraryLoan;
use App\Models\Student;
use App\Models\User;
use App\Services\Library\LibraryService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Librarian: issue and return books, record and settle fines.
 */
class LibraryLoans extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Campus';

    protected static ?int $navigationSort = 21;

    protected static ?string $slug = 'campus/library-loans';

    protected static ?string $navigationLabel = 'Library loans';

    protected string $view = 'filament.pages.campus.library-loans';

    public string $roll = '';

    public string $bookTitle = '';

    public ?string $dueOn = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'library_loans:manage');
    }

    /**
     * @return Collection<int, LibraryLoan>
     */
    public function openLoans(): Collection
    {
        return LibraryLoan::query()->with('student.user')->where(fn ($query) => $query->whereNull('returned_on')->orWhere(fn ($fine) => $fine->where('fine_amount', '>', 0)->whereNull('fine_settled_at')))->orderBy('due_on')->get();
    }

    public function issue(): void
    {
        $this->run(function (): string {
            $student = Student::query()->where('roll_number', trim($this->roll))->firstOrFail();
            app(LibraryService::class)->issue($this->user(), $student, $this->bookTitle, $this->dueOn ?? now()->addDays(14)->toDateString());
            $this->bookTitle = '';

            return 'Book issued.';
        });
    }

    public function returned(int $loanId, ?string $fine = null): void
    {
        $this->run(function () use ($loanId, $fine): string {
            app(LibraryService::class)->markReturned($this->user(), LibraryLoan::query()->findOrFail($loanId), (float) $fine);

            return 'Marked as returned.';
        });
    }

    public function settleFine(int $loanId): void
    {
        $this->run(function () use ($loanId): string {
            app(LibraryService::class)->settleFine($this->user(), LibraryLoan::query()->findOrFail($loanId));

            return 'Fine settled.';
        });
    }

    /**
     * @param  callable(): string  $callback
     */
    protected function run(callable $callback): void
    {
        try {
            $message = $callback();
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            Notification::make()->danger()->title('Student or loan not found.')->send();

            return;
        }

        Notification::make()->success()->title($message)->send();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
