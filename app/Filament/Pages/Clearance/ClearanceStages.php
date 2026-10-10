<?php

namespace App\Filament\Pages\Clearance;

use App\Models\ClearanceStage;
use App\Models\User;
use App\Services\Clearance\ClearanceStageService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Super admin: reorder, activate or make stages skippable (spec §8.2).
 */
class ClearanceStages extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'settings/clearance-stages';

    protected string $view = 'filament.pages.clearance.stages';

    public static function getNavigationLabel(): string
    {
        return 'Clearance stages';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'clearance_stage:manage');
    }

    /**
     * @return Collection<int, ClearanceStage>
     */
    public function stages(): Collection
    {
        return app(ClearanceStageService::class)->all($this->user());
    }

    public function move(int $id, string $direction): void
    {
        app(ClearanceStageService::class)->move($this->user(), $id, $direction === 'up' ? -1 : 1);
        Notification::make()->success()->title('Order updated.')->send();
    }

    public function toggle(int $id, string $field): void
    {
        app(ClearanceStageService::class)->toggle($this->user(), $id, $field);
        Notification::make()->success()->title('Saved.')->send();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
