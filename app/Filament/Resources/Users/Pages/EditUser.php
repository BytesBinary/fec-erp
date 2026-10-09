<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\RoleKey;
use App\Enums\ScopeType;
use App\Exceptions\Domain\DomainException;
use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Users\UserResource;
use App\Models\Course;
use App\Models\Department;
use App\Models\Hall;
use App\Models\User;
use App\Services\Users\RoleService;
use App\Services\Users\UserService;
use App\Support\Authorization\Authorizer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Spatie\Permission\Models\Role;

/**
 * @property User $record
 */
class EditUser extends EditRecord
{
    use SavesThroughDomainService;

    protected static string $resource = UserResource::class;

    protected static function domainService(): string
    {
        return UserService::class;
    }

    public function getSubheading(): ?string
    {
        $roles = app(RoleService::class)->rolesOf($this->actingUser(), $this->record);

        if ($roles === []) {
            return 'No roles yet — this user cannot sign in to the panel.';
        }

        return 'Roles: '.collect($roles)
            ->map(fn (array $role): string => $this->describeRole($role))
            ->implode(' · ');
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->assignRoleAction(),
            $this->revokeRoleAction(),
            ActionGroup::make([
                Action::make('deactivate')
                    ->label('Deactivate account')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->record->is_active && $this->can('user:deactivate'))
                    ->action(fn () => $this->runDomainAction(
                        fn () => app(UserService::class)->deactivate($this->actingUser(), $this->record),
                        'Account deactivated.',
                    )),
                Action::make('reactivate')
                    ->label('Reactivate account')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (): bool => ! $this->record->is_active && $this->can('user:deactivate'))
                    ->action(fn () => $this->runDomainAction(
                        fn () => app(UserService::class)->reactivate($this->actingUser(), $this->record),
                        'Account reactivated.',
                    )),
            ]),
        ];
    }

    protected function assignRoleAction(): Action
    {
        return Action::make('assignRole')
            ->label('Assign role')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->visible(fn (): bool => $this->can('role:assign'))
            ->schema([
                Select::make('role')
                    ->options(fn (): array => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name', 'name')
                        ->map(fn (string $name): string => RoleKey::tryFrom($name)?->label() ?? $name)
                        ->all())
                    ->required()
                    ->live(),
                Select::make('scope_ids')
                    ->label(fn (Get $get): string => match ($this->scopeTypeOf($get('role'))) {
                        ScopeType::Department => 'Departments',
                        ScopeType::Hall => 'Halls',
                        default => 'Extra courses (assigned courses are included automatically)',
                    })
                    ->multiple()
                    ->options(fn (Get $get): array => match ($this->scopeTypeOf($get('role'))) {
                        ScopeType::Department => Department::query()->orderBy('name')->pluck('name', 'id')->all(),
                        ScopeType::Hall => Hall::query()->orderBy('name')->pluck('name', 'id')->all(),
                        ScopeType::Course => Course::query()->orderBy('code')->pluck('code', 'id')->all(),
                        default => [],
                    })
                    ->required(fn (Get $get): bool => in_array($this->scopeTypeOf($get('role')), [ScopeType::Department, ScopeType::Hall], true))
                    ->visible(fn (Get $get): bool => in_array($this->scopeTypeOf($get('role')), [ScopeType::Department, ScopeType::Hall, ScopeType::Course], true)),
            ])
            ->action(fn (array $data) => $this->runDomainAction(
                fn () => app(RoleService::class)->assign($this->actingUser(), $this->record, $data['role'], array_map('intval', $data['scope_ids'] ?? [])),
                'Role assigned.',
            ));
    }

    protected function revokeRoleAction(): Action
    {
        return Action::make('revokeRole')
            ->label('Revoke role')
            ->icon(Heroicon::OutlinedShieldExclamation)
            ->color('danger')
            ->visible(fn (): bool => $this->can('role:revoke') && $this->record->roles()->exists())
            ->schema([
                Select::make('role')
                    ->options(fn (): array => $this->record->roles()->pluck('name', 'name')
                        ->map(fn (string $name): string => RoleKey::tryFrom($name)?->label() ?? $name)
                        ->all())
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(fn (array $data) => $this->runDomainAction(
                fn () => app(RoleService::class)->revoke($this->actingUser(), $this->record, $data['role']),
                'Role revoked.',
            ));
    }

    /**
     * @param  callable(): mixed  $callback
     */
    protected function runDomainAction(callable $callback, string $success): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            $details = collect($exception->context['errors'] ?? [])->flatten()->implode(' ');

            Notification::make()->danger()->title($exception->getMessage())->body($details ?: null)->send();

            return;
        }

        $this->record->refresh();

        Notification::make()->success()->title($success)->send();
    }

    /**
     * @param  array{role: string, scope_type: string, scope_ids: list<int>}  $role
     */
    protected function describeRole(array $role): string
    {
        $label = RoleKey::tryFrom($role['role'])?->label() ?? $role['role'];

        if ($role['scope_ids'] === []) {
            return $label;
        }

        $names = match (ScopeType::from($role['scope_type'])) {
            ScopeType::Department => Department::query()->whereKey($role['scope_ids'])->pluck('code'),
            ScopeType::Hall => Hall::query()->whereKey($role['scope_ids'])->pluck('code'),
            ScopeType::Course => Course::query()->whereKey($role['scope_ids'])->pluck('code'),
            default => collect($role['scope_ids']),
        };

        return "{$label} ({$names->implode(', ')})";
    }

    protected function scopeTypeOf(?string $role): ?ScopeType
    {
        return $role === null ? null : (RoleKey::tryFrom($role)?->defaultScope() ?? ScopeType::Global);
    }

    protected function can(string $permission): bool
    {
        return app(Authorizer::class)->allows($this->actingUser(), $permission);
    }
}
