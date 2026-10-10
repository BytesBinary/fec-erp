<?php

namespace App\Filament\Pages\Clearance;

use App\Enums\ClearanceStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\ClearanceRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\Clearance\ClearanceService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Clearance → My clearance: live timeline, resubmit after a rejection.
 */
class MyClearance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Clearance';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'clearance/my';

    protected string $view = 'filament.pages.clearance.my-clearance';

    public static function getNavigationLabel(): string
    {
        return 'My clearance';
    }

    public function getTitle(): string
    {
        return __('erp.clearance.my_title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && Student::query()->where('user_id', $user->getKey())->exists();
    }

    public function clearance(): ?ClearanceRequest
    {
        return app(ClearanceService::class)->mine($this->user());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(ClearanceRequest $request): array
    {
        return app(ClearanceService::class)->timeline($request);
    }

    public function resubmitAction(): Action
    {
        return Action::make('resubmit')
            ->label('Resubmit')
            ->visible(fn (): bool => $this->clearance()?->status === ClearanceStatus::Rejected)
            ->action(fn () => $this->run(fn () => app(ClearanceService::class)->resubmit($this->user(), $this->clearance()), 'Resubmitted. The request continues at the stage that rejected it.'));
    }

    public function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel request')
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->clearance()?->status?->isActive() === true && ! $this->clearance()->approvals()->where('decision', 'approved')->exists() && $this->clearance()->status !== ClearanceStatus::Printed)
            ->schema([Textarea::make('reason')->label('Reason (optional)')->maxLength(255)])
            ->action(fn (array $data) => $this->run(fn () => app(ClearanceService::class)->cancel($this->user(), $this->clearance(), $data['reason'] ?? null), 'Request cancelled.'));
    }

    /**
     * @param  callable(): mixed  $callback
     */
    protected function run(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($success)->send();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
