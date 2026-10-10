<?php

namespace App\Filament\Pages\Clearance;

use App\Enums\ClearanceStatus;
use App\Models\ClearanceRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\Clearance\ClearanceService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Administration office desk: find a student's clearance, print it, hand it over.
 */
class ClearanceDesk extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPrinter;

    protected static string|UnitEnum|null $navigationGroup = 'Clearance';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'clearance/desk';

    protected string $view = 'filament.pages.clearance.desk';

    public string $search = '';

    public ?int $departmentId = null;

    public string $session = '';

    public string $status = 'ready_for_collection';

    public static function getNavigationLabel(): string
    {
        return 'Clearance desk';
    }

    public function getTitle(): string
    {
        return 'Clearance desk';
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'clearance:search');
    }

    /**
     * @return Collection<int, ClearanceRequest>
     */
    public function requests(): Collection
    {
        return app(ClearanceService::class)->search($this->user(), [
            'q' => $this->search,
            'department_id' => $this->departmentId,
            'session' => $this->session,
            'status' => $this->status,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function statuses(): array
    {
        return ['all' => 'All statuses'] + collect(ClearanceStatus::cases())->mapWithKeys(fn (ClearanceStatus $status): array => [$status->value => $status->label()])->all();
    }

    /**
     * @return array<int, string>
     */
    public function departments(): array
    {
        return Department::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public function sessions(): array
    {
        return \App\Models\Batch::query()->distinct()->orderBy('session')->pluck('session', 'session')->all();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
