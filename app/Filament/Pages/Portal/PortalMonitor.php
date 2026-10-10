<?php

namespace App\Filament\Pages\Portal;

use App\Exceptions\Domain\DomainException;
use App\Models\PortalPublication;
use App\Models\User;
use App\Services\ResultPortal\PortalMonitor as PortalMonitorService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Exam list, daily check, publications and the pulls they started. The two
 * buttons: "First-time sync" saves the portal's exam list; "Check for new
 * results" re-reads it, finds new exams and confirms them with probe students.
 */
class PortalMonitor extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Academic';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Portal monitor';

    protected static ?string $slug = 'portal-monitor';

    protected string $view = 'filament.pages.portal.portal-monitor';

    public function getTitle(): string
    {
        return 'Result portal monitor';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'portal_monitor:view');
    }

    public function canManage(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'portal_monitor:manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $user = Auth::user();
        assert($user instanceof User);

        return app(PortalMonitorService::class)->status($user);
    }

    public function runPublication(int $publicationId): void
    {
        $this->guarded(function () use ($publicationId): void {
            $count = app(PortalMonitorService::class)->runPublication(Auth::user(), PortalPublication::query()->findOrFail($publicationId));
            Notification::make()->title("{$count} student pull(s) queued")->success()->send();
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncCatalog')
                ->label('First-time sync')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (): bool => $this->canManage())
                ->requiresConfirmation()
                ->modalDescription('Fetches the exam list of CSE, EEE and Civil from the portal and saves it. Old exams are stored as already known; they do not count as new publications.')
                ->action(fn () => $this->guarded(function (): void {
                    $report = app(PortalMonitorService::class)->syncCatalog(Auth::user());
                    Notification::make()->title("{$report['total']} exams saved ({$report['new']} new)")->success()->send();
                })),
            Action::make('checkNow')
                ->label('Check for new results')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->visible(fn (): bool => $this->canManage())
                ->requiresConfirmation()
                ->modalDescription('Reads the exam list again, finds exams that were not there before and checks with a few probe students whether their results are visible.')
                ->action(fn () => $this->guarded(function (): void {
                    $report = app(PortalMonitorService::class)->checkNow(Auth::user());
                    $notification = Notification::make()->title("{$report['new_exams']} new exam(s), {$report['confirmed']} publication(s) confirmed");
                    $report['failed'] ? $notification->body((string) $report['message'])->danger() : $notification->success();
                    $notification->send();
                })),
        ];
    }

    protected function guarded(callable $action): void
    {
        try {
            $action();
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
