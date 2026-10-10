<?php

namespace App\Filament\Pages\Clearance;

use App\Models\ClearanceRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\Clearance\ClearanceService;
use App\Services\Clearance\StaffSignatureService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * "Clearance requests waiting for me" for every approver role (spec §8.9).
 */
class PendingApprovals extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Clearance';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'clearance/pending';

    protected string $view = 'filament.pages.clearance.pending-approvals';

    public string $search = '';

    public ?int $departmentId = null;

    public static function getNavigationLabel(): string
    {
        return 'Waiting for me';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::waitingCount();

        return $count > 0 ? (string) $count : null;
    }

    public function getTitle(): string
    {
        return __('erp.clearance.pending_title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'clearance:approve');
    }

    public static function waitingCount(): int
    {
        $user = Auth::user();

        if (! $user instanceof User || ! static::canAccess()) {
            return 0;
        }

        return app(ClearanceService::class)->pendingFor($user)->count();
    }

    /**
     * @return Collection<int, ClearanceRequest>
     */
    public function requests(): Collection
    {
        $term = mb_strtolower(trim($this->search));

        return app(ClearanceService::class)->pendingFor($this->user())
            ->when($this->departmentId !== null, fn (Collection $rows) => $rows->where('student.department_id', $this->departmentId))
            ->when($term !== '', fn (Collection $rows) => $rows->filter(fn (ClearanceRequest $request): bool => str_contains(mb_strtolower($request->student->user->name.' '.$request->student->roll_number.' '.$request->request_no), $term)))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function departments(): array
    {
        return Department::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function hasSignature(): bool
    {
        return app(StaffSignatureService::class)->has($this->user());
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
