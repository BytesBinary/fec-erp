<?php

namespace App\Filament\Pages\Security;

use App\Models\User;
use App\Services\Security\MfaPolicy;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Super admin: make 2FA mandatory per role (spec §3A.2). Off by default.
 */
class TwoFactorPolicy extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Security & Access';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'security/two-factor-policy';

    protected string $view = 'filament.pages.security.two-factor-policy';

    public static function getNavigationLabel(): string
    {
        return __('erp.security.policy_nav');
    }

    public function getTitle(): string
    {
        return __('erp.security.policy_title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'security:manage');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{role: Role, required: bool}>
     */
    public function getRoles(): \Illuminate\Support\Collection
    {
        $policy = app(MfaPolicy::class);

        return Role::query()->where('guard_name', 'web')->orderBy('name')->get()
            ->map(fn (Role $role): array => ['role' => $role, 'required' => $policy->isRoleRequired($role)]);
    }

    public function toggleRole(int $roleId): void
    {
        abort_unless(static::canAccess(), 403);

        $role = Role::query()->findOrFail($roleId);
        $policy = app(MfaPolicy::class);
        $policy->setRoleRequired($role, ! $policy->isRoleRequired($role));

        Notification::make()->success()->title('Policy updated.')->send();
    }
}
