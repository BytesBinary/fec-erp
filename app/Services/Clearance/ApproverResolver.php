<?php

namespace App\Services\Clearance;

use App\Enums\ClearanceStatus;
use App\Enums\ScopeType;
use App\Models\ClearanceRequest;
use App\Models\ClearanceStage;
use App\Models\HallResidency;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use Illuminate\Support\Collection;

/**
 * Who may act on a clearance request at its current stage (spec §3.2, §8.2):
 * the user must hold the stage's approver role AND the role's scope must
 * cover the student (hall of residence / department).
 */
class ApproverResolver
{
    public function __construct(protected Authorizer $authorizer) {}

    public function canAct(User $user, ClearanceRequest $request): bool
    {
        if ($request->status !== ClearanceStatus::Pending) {
            return false;
        }

        $stage = $request->currentStage;

        return $stage !== null && $this->matchesStage($user, $stage, $request);
    }

    public function matchesStage(User $user, ClearanceStage $stage, ClearanceRequest $request): bool
    {
        $role = $stage->approverRole;

        if ($role === null || ! $user->hasRole($role->name) || $this->authorizer->denies($user, 'clearance:approve')) {
            return false;
        }

        return match ($stage->scope_rule) {
            'hall' => $this->hallOf($request) !== null && in_array($this->hallOf($request), $this->scopeIds($user, $role->getKey(), ScopeType::Hall), true),
            'department' => in_array($request->student->department_id, $this->scopeIds($user, $role->getKey(), ScopeType::Department), true),
            default => true,
        };
    }

    /**
     * Active users who can act at `$stage` for `$request` (notification targets).
     *
     * @return Collection<int, User>
     */
    public function approversFor(ClearanceRequest $request, ClearanceStage $stage): Collection
    {
        $roleName = $stage->approverRole?->name;

        if ($roleName === null) {
            return collect();
        }

        return User::query()
            ->role($roleName)
            ->where('is_active', true)
            ->with('roleScopes')
            ->get()
            ->filter(fn (User $user): bool => $this->matchesStage($user, $stage, $request))
            ->values();
    }

    public function hallOf(ClearanceRequest $request): ?int
    {
        return HallResidency::query()->current()->where('student_id', $request->student_id)->value('hall_id');
    }

    /**
     * @return list<int>
     */
    protected function scopeIds(User $user, int $roleId, ScopeType $type): array
    {
        return $user->roleScopes()
            ->where('role_id', $roleId)
            ->where('scope_type', $type->value)
            ->pluck('scope_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }
}
