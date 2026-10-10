<?php

namespace App\Filament\Pages\Clearance;

use App\Exceptions\Domain\DomainException;
use App\Models\Student;
use App\Models\User;
use App\Services\Clearance\ClearanceService;
use App\Services\Clearance\EligibilityResult;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Clearance → Apply: eligibility check with exact reasons, then submit.
 */
class Apply extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Clearance';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'clearance/apply';

    protected string $view = 'filament.pages.clearance.apply';

    public static function getNavigationLabel(): string
    {
        return 'Apply';
    }

    public function getTitle(): string
    {
        return __('erp.clearance.apply_title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'clearance:apply') && Student::query()->where('user_id', $user->getKey())->exists();
    }

    public function mount(): void
    {
        $existing = app(ClearanceService::class)->mine($this->user());

        if ($existing !== null && $existing->status->isActive()) {
            $this->redirect(MyClearance::getUrl());
        }
    }

    public function eligibility(): EligibilityResult
    {
        return app(ClearanceService::class)->checkEligibility($this->user());
    }

    public function student(): Student
    {
        return Student::query()->with(['user', 'department', 'program', 'profile'])->where('user_id', $this->user()->getKey())->firstOrFail();
    }

    public function submitAction(): Action
    {
        return Action::make('submit')
            ->label('Submit clearance request')
            ->requiresConfirmation()
            ->modalHeading('Submit your clearance request?')
            ->modalDescription('Your name, parents\' names and date of birth are locked after you apply.')
            ->action(function (): void {
                try {
                    app(ClearanceService::class)->apply($this->user());
                } catch (DomainException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                $this->redirect(MyClearance::getUrl());
            });
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
