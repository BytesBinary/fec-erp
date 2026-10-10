<?php

namespace App\Filament\Pages\Settings;

use App\Exceptions\Domain\DomainException;
use App\Filament\Pages\Security\TwoFactorSettings;
use App\Models\McpIntegration;
use App\Models\User;
use App\Services\Mcp\ClientSnippets;
use App\Services\Mcp\IntegrationService;
use App\Services\Mcp\McpSettingsService;
use App\Services\Security\TwoFactorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use UnitEnum;

/**
 * Settings → AI Integrations (MCP): the single place where a user connects,
 * sees and stops AI clients (spec §4.5). Needs 2FA.
 */
class AiIntegrations extends Page
{
    public const STEPS = ['client', 'limits', 'confirm', 'connect', 'test'];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'settings/ai-integrations';

    protected string $view = 'filament.pages.settings.ai-integrations';

    public bool $wizardOpen = false;

    public int $step = 1;

    public string $clientType = 'claude_code';

    public string $integrationName = '';

    public string $accessLevel = 'full';

    public int $expiresInDays = 90;

    public string $totpCode = '';

    public ?string $wizardError = null;

    /**
     * The plain token — held only until the wizard is closed, shown once.
     */
    #[Locked]
    public ?string $newToken = null;

    #[Locked]
    public ?int $newIntegrationId = null;

    #[Locked]
    public ?int $activityIntegrationId = null;

    #[Locked]
    public bool $connected = false;

    #[Locked]
    public ?int $testStartedAt = null;

    public static function getNavigationLabel(): string
    {
        return __('erp.mcp.nav');
    }

    public function getTitle(): string
    {
        return __('erp.mcp.title');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(McpSettingsService::class)->enabledForUser($user);
    }

    public function hasTwoFactor(): bool
    {
        return app(TwoFactorService::class)->isEnabled($this->user());
    }

    public function setupTwoFactorUrl(): string
    {
        return TwoFactorSettings::getUrl(['from' => 'ai-integrations']);
    }

    /**
     * @return array<string, array{label: string, description: string}>
     */
    public function clients(): array
    {
        return app(ClientSnippets::class)->clients();
    }

    /**
     * @return array<int, string>
     */
    public function expiryOptions(): array
    {
        $options = collect(config('mcp_access.expiry_options_days'))->mapWithKeys(fn (int $days): array => [$days => "{$days} days"])->all();

        if (app(McpSettingsService::class)->settings()->allow_never_expire) {
            $options[0] = 'Never expires';
        }

        return $options;
    }

    /**
     * @return Collection<int, McpIntegration>
     */
    public function integrations(): Collection
    {
        return app(IntegrationService::class)->listFor($this->user());
    }

    public function callsLastWeek(McpIntegration $integration): int
    {
        return app(IntegrationService::class)->callsInLastDays($integration, 7);
    }

    public function activeCount(): int
    {
        return app(IntegrationService::class)->activeCount($this->user());
    }

    public function maxIntegrations(): int
    {
        return app(McpSettingsService::class)->settings()->max_integrations_per_user;
    }

    public function startWizard(): void
    {
        $this->resetWizard();
        $this->wizardOpen = true;
        $this->chooseClient('claude_code');
    }

    public function closeWizard(): void
    {
        $this->resetWizard();
    }

    public function chooseClient(string $client): void
    {
        $this->clientType = array_key_exists($client, $this->clients()) ? $client : 'other';
        $this->integrationName = app(ClientSnippets::class)->label($this->clientType).' – '.__('erp.mcp.default_device');
    }

    public function next(): void
    {
        $this->wizardError = null;

        if ($this->step === 2 && trim($this->integrationName) === '') {
            $this->wizardError = __('erp.mcp.name_required');

            return;
        }

        if ($this->step === 3) {
            $this->createIntegration();

            return;
        }

        if ($this->step === 4) {
            $this->step = 5;
            $this->testStartedAt = now()->getTimestamp();

            return;
        }

        $this->step = min(5, $this->step + 1);
    }

    public function back(): void
    {
        if ($this->step > 1 && $this->step < 4) {
            $this->step--;
            $this->wizardError = null;
        }
    }

    public function createIntegration(): void
    {
        try {
            $created = app(IntegrationService::class)->create(
                $this->user(),
                $this->integrationName,
                $this->clientType,
                $this->accessLevel,
                $this->expiresInDays === 0 ? null : $this->expiresInDays,
                $this->totpCode,
            );
        } catch (DomainException $exception) {
            $this->wizardError = $exception->getMessage();

            return;
        }

        $this->newToken = $created['token'];
        $this->newIntegrationId = $created['integration']->getKey();
        $this->totpCode = '';
        $this->step = 4;
        $this->wizardError = null;
    }

    /**
     * @return array{label: string, file: string, language: string, snippet: string, steps: list<string>}|null
     */
    public function snippet(): ?array
    {
        return $this->newToken === null ? null : app(ClientSnippets::class)->for($this->clientType, $this->newToken);
    }

    /**
     * Polled while the wizard waits for the first successful MCP request.
     */
    public function checkConnection(): void
    {
        if ($this->newIntegrationId === null) {
            return;
        }

        $this->connected = McpIntegration::query()->where('user_id', $this->user()->getKey())->whereKey($this->newIntegrationId)->whereNotNull('first_connected_at')->exists();
    }

    public function troubleshooting(): bool
    {
        return ! $this->connected && $this->testStartedAt !== null && now()->getTimestamp() - $this->testStartedAt >= 120;
    }

    public function stopAction(): Action
    {
        return Action::make('stop')
            ->label(__('erp.mcp.stop'))
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('erp.mcp.stop_heading'))
            ->modalDescription(__('erp.mcp.stop_description'))
            ->action(function (array $arguments): void {
                $integration = McpIntegration::query()->where('user_id', $this->user()->getKey())->findOrFail($arguments['integration']);
                $this->guarded(fn () => app(IntegrationService::class)->revoke($this->user(), $integration, 'Stopped by user'), __('erp.mcp.stopped'));
            });
    }

    public function stopAllAction(): Action
    {
        return Action::make('stopAll')
            ->label(__('erp.mcp.stop_all'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('erp.mcp.stop_all'))
            ->modalDescription(__('erp.mcp.stop_all_description'))
            ->visible(fn (): bool => $this->activeCount() > 0)
            ->action(fn () => $this->guarded(fn () => app(IntegrationService::class)->stopAllFor($this->user(), $this->user(), 'Stopped by user'), __('erp.mcp.stopped_all')));
    }

    public function renameAction(): Action
    {
        return Action::make('rename')
            ->label(__('erp.mcp.rename'))
            ->color('gray')
            ->size('sm')
            ->fillForm(fn (array $arguments): array => ['name' => McpIntegration::query()->where('user_id', $this->user()->getKey())->find($arguments['integration'])?->name])
            ->schema([TextInput::make('name')->required()->maxLength(120)])
            ->action(function (array $data, array $arguments): void {
                $integration = McpIntegration::query()->where('user_id', $this->user()->getKey())->findOrFail($arguments['integration']);
                $this->guarded(fn () => app(IntegrationService::class)->rename($this->user(), $integration, $data['name']), __('erp.mcp.renamed'));
            });
    }

    public function showActivity(int $integrationId): void
    {
        $this->activityIntegrationId = McpIntegration::query()->where('user_id', $this->user()->getKey())->findOrFail($integrationId)->getKey();
    }

    public function hideActivity(): void
    {
        $this->activityIntegrationId = null;
    }

    /**
     * Audit entries of the integration whose activity is open.
     *
     * @return Collection<int, \App\Models\AuditLog>
     */
    public function activityLogs(): Collection
    {
        if ($this->activityIntegrationId === null) {
            return collect();
        }

        return app(IntegrationService::class)->activity($this->user(), McpIntegration::query()->where('user_id', $this->user()->getKey())->findOrFail($this->activityIntegrationId));
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

    protected function resetWizard(): void
    {
        $this->reset(['wizardOpen', 'step', 'totpCode', 'wizardError', 'newToken', 'newIntegrationId', 'connected', 'testStartedAt', 'accessLevel', 'expiresInDays']);
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
