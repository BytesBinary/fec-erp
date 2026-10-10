<?php

namespace App\Filament\Pages\Clearance;

use App\Enums\ClearanceStatus;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\DomainException;
use App\Models\ClearanceRequest;
use App\Models\User;
use App\Services\Clearance\ApproverResolver;
use App\Services\Clearance\ClearanceService;
use App\Services\Clearance\HashChain;
use App\Services\Halls\HallResidencyService;
use App\Services\Library\LibraryService;
use App\Support\Authorization\Authorizer;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * One clearance request for staff: timeline, the student's dues and, for the
 * approver of the current stage, approve / reject. Opening a request outside
 * the user's scope is a 403.
 */
class ViewClearance extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'clearance/requests/{record}';

    protected string $view = 'filament.pages.clearance.view-clearance';

    #[Locked]
    public int $recordId = 0;

    /**
     * The optimistic-lock version the page was rendered with.
     */
    #[Locked]
    public int $loadedVersion = 0;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'clearance:view');
    }

    public function mount(int|string $record): void
    {
        $request = app(ClearanceService::class)->get($this->user(), (int) $record);

        $this->recordId = $request->getKey();
        $this->loadedVersion = $request->version;
    }

    public function getTitle(): string
    {
        return $this->recordId === 0 ? 'Clearance request' : 'Clearance '.$this->clearance()->request_no;
    }

    public function clearance(): ClearanceRequest
    {
        return app(ClearanceService::class)->get($this->user(), $this->recordId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(): array
    {
        return app(ClearanceService::class)->timeline($this->clearance());
    }

    public function canDecide(): bool
    {
        return app(ApproverResolver::class)->canAct($this->user(), $this->clearance());
    }

    /**
     * Dues the approver can see for this student.
     *
     * @return array{hall: Collection<int, \App\Models\HallDue>|null, library: array<string, mixed>|null}
     */
    public function dues(): array
    {
        $student = $this->clearance()->student;
        $user = $this->user();
        $authorizer = app(Authorizer::class);

        $hall = null;
        $library = null;

        try {
            $hall = app(HallResidencyService::class)->duesOf($user, $student);
        } catch (DomainException) {
        }

        if ($authorizer->allows($user, 'library_dues:view')) {
            $library = app(LibraryService::class)->duesOf($user, $student);
        }

        return ['hall' => $hall, 'library' => $library];
    }

    /**
     * @return array{intact: bool, broken_at: ?int, checked: int}
     */
    public function integrity(): array
    {
        return app(HashChain::class)->verify($this->clearance());
    }

    public function canPrint(): bool
    {
        return app(Authorizer::class)->allows($this->user(), 'clearance:print', $this->clearance())
            && in_array($this->clearance()->status, [ClearanceStatus::ReadyForCollection, ClearanceStatus::Printed, ClearanceStatus::Collected], true);
    }

    public function printAction(): Action
    {
        return Action::make('print')
            ->label(fn (): string => $this->clearance()->prints()->exists() ? 'Reprint as duplicate' : 'Print')
            ->icon('heroicon-o-printer')
            ->visible(fn (): bool => $this->canPrint() && $this->clearance()->status !== ClearanceStatus::Collected)
            ->action(fn () => $this->printCopy(false));
    }

    public function printOriginalAction(): Action
    {
        return Action::make('printOriginal')
            ->label('Reprint without DUPLICATE')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->canPrint() && $this->clearance()->status === ClearanceStatus::Printed && app(Authorizer::class)->isSuperAdmin($this->user()))
            ->action(fn () => $this->printCopy(true));
    }

    public function collectAction(): Action
    {
        return Action::make('collect')
            ->label('Mark collected')
            ->color('success')
            ->modalHeading('Hand the clearance over?')
            ->modalDescription('Confirm the Principal signed it, it is sealed and the student showed their ID.')
            ->schema([Checkbox::make('id_verified')->label('Student ID verified')])
            ->visible(fn (): bool => $this->clearance()->status === ClearanceStatus::Printed && app(Authorizer::class)->allows($this->user(), 'clearance:mark_collected', $this->clearance()))
            ->action(fn (array $data) => $this->decide(fn () => app(ClearanceService::class)->markCollected($this->user(), $this->recordId, (bool) ($data['id_verified'] ?? false)), 'Marked as collected.'));
    }

    protected function printCopy(bool $asOriginal): void
    {
        try {
            $print = app(ClearanceService::class)->recordPrint($this->user(), $this->recordId, 'html', $asOriginal);
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $this->redirect(route('clearance.print', ['clearanceRequest' => $this->recordId, 'print' => $print->getKey()]));
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve this clearance?')
            ->modalDescription('Your signature image will be attached to this clearance.')
            ->schema([Textarea::make('remarks')->label('Remarks (optional)')->maxLength(500)])
            ->visible(fn (): bool => $this->canDecide())
            ->action(fn (array $data) => $this->decide(fn () => app(ClearanceService::class)->approve($this->user(), $this->recordId, $data['remarks'] ?? null, $this->loadedVersion), 'Approved.'));
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->color('danger')
            ->modalHeading('Reject this clearance?')
            ->schema([Textarea::make('reason')->label('Reason (required)')->required()->maxLength(500)])
            ->visible(fn (): bool => $this->canDecide())
            ->action(fn (array $data) => $this->decide(fn () => app(ClearanceService::class)->reject($this->user(), $this->recordId, $data['reason'], $this->loadedVersion), 'Rejected. The student has been notified.'));
    }

    /**
     * @param  callable(): mixed  $callback
     */
    protected function decide(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (ConflictException) {
            Notification::make()->warning()->title('This request was changed by someone else. The page was reloaded.')->send();
            $this->loadedVersion = $this->clearance()->version;

            return;
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($success)->send();
        $this->redirect(PendingApprovals::canAccess() ? PendingApprovals::getUrl() : static::getUrl(['record' => $this->recordId]));
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
