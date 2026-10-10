<?php

namespace App\Filament\Pages\Security;

use App\Exceptions\Domain\DomainException;
use App\Models\McpIntegration;
use App\Models\User;
use App\Services\Mcp\ClientSnippets;
use App\Services\Mcp\IntegrationService;
use App\Services\Mcp\McpSettingsService;
use App\Support\Authorization\Authorizer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Super admin oversight of every AI integration, the global and per-role MCP
 * switches and a usage overview (spec §4.5).
 */
class McpOversight extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    protected static string|UnitEnum|null $navigationGroup = 'Security & Access';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'security/mcp-integrations';

    protected string $view = 'filament.pages.security.mcp-oversight';

    public string $userFilter = '';

    public string $roleFilter = '';

    public string $clientFilter = '';

    public string $statusFilter = '';

    public static function getNavigationLabel(): string
    {
        return __('erp.mcp.oversight_nav');
    }

    public function getTitle(): string
    {
        return __('erp.mcp.oversight_title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(Authorizer::class)->allows($user, 'mcp_integration:manage_all');
    }

    /**
     * @return Collection<int, McpIntegration>
     */
    public function integrations(): Collection
    {
        return app(IntegrationService::class)->adminQuery($this->user(), [
            'user' => $this->userFilter, 'role' => $this->roleFilter, 'client' => $this->clientFilter, 'status' => $this->statusFilter,
        ])->limit(200)->get();
    }

    /**
     * @return array{active: int, calls_per_day: array<string, int>, denied_per_day: array<string, int>}
     */
    public function usage(): array
    {
        return app(IntegrationService::class)->usageOverview($this->user());
    }

    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::query()->where('guard_name', 'web')->orderBy('name')->get();
    }

    public function roleEnabled(Role $role): bool
    {
        return app(McpSettingsService::class)->roleEnabled($role);
    }

    public function globalEnabled(): bool
    {
        return app(McpSettingsService::class)->globallyEnabled();
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function clients(): array
    {
        return app(ClientSnippets::class)->clients();
    }

    public function toggleGlobal(): void
    {
        $this->guarded(fn () => app(McpSettingsService::class)->setGlobal($this->user(), ! $this->globalEnabled()), 'Saved.');
    }

    public function toggleRole(int $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);
        $this->guarded(fn () => app(McpSettingsService::class)->setRole($this->user(), $role, ! $this->roleEnabled($role)), 'Saved.');
    }

    public function toggleNeverExpire(): void
    {
        $this->guarded(fn () => app(McpSettingsService::class)->configure($this->user(), allowNeverExpire: ! app(McpSettingsService::class)->settings()->allow_never_expire), 'Saved.');
    }

    public function revokeAction(): Action
    {
        return Action::make('revoke')
            ->label('Revoke')
            ->color('danger')
            ->size('sm')
            ->modalHeading('Revoke this integration')
            ->modalDescription('The owner is notified with your reason. The AI client loses access immediately.')
            ->schema([Textarea::make('reason')->label('Reason')->required()->maxLength(255)])
            ->action(function (array $data, array $arguments): void {
                $integration = McpIntegration::query()->findOrFail($arguments['integration']);
                $this->guarded(fn () => app(IntegrationService::class)->revoke($this->user(), $integration, $data['reason']), 'Integration revoked and the user was notified.');
            });
    }

    /**
     * @param  callable(): mixed  $callback
     */
    protected function guarded(callable $callback, string $success): void
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
