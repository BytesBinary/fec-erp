<?php

namespace App\Filament\Pages\Security;

use App\Events\McpIntegrationsStopRequested;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Audit\AuditLogger;
use App\Services\Security\SessionTracker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Settings → Devices: every active login of the signed-in user, with
 * per-device and "all other devices" logout (spec §3A.1).
 */
class Devices extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 60;

    protected static ?string $slug = 'security/devices';

    protected string $view = 'filament.pages.security.devices';

    public static function getNavigationLabel(): string
    {
        return __('erp.security.devices_nav');
    }

    public function getTitle(): string
    {
        return __('erp.security.devices_title');
    }

    /**
     * @return Collection<int, UserSession>
     */
    public function getSessions(): Collection
    {
        return app(SessionTracker::class)->activeFor($this->user());
    }

    public function currentSessionHash(): ?string
    {
        return app(SessionTracker::class)->find(request())?->session_hash;
    }

    public function logoutDeviceAction(): Action
    {
        return Action::make('logoutDevice')
            ->label(__('erp.security.log_out'))
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading(__('erp.security.confirm_logout_device'))
            ->action(fn (array $arguments) => $this->logoutDevice((int) ($arguments['session'] ?? 0)));
    }

    public function logoutOthersAction(): Action
    {
        return Action::make('logoutOthers')
            ->label(__('erp.security.logout_others'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('erp.security.logout_others'))
            ->modalDescription(__('erp.security.confirm_logout_others'))
            ->schema([
                Checkbox::make('stop_integrations')->label(__('erp.security.also_stop_integrations')),
            ])
            ->action(fn (array $data) => $this->logoutOthers((bool) ($data['stop_integrations'] ?? false)));
    }

    public function logoutDevice(int $sessionId): void
    {
        $session = UserSession::query()->where('user_id', $this->user()->getKey())->whereKey($sessionId)->first();

        if ($session === null || $session->session_hash === $this->currentSessionHash()) {
            return;
        }

        app(SessionTracker::class)->revoke($session, $this->user(), 'logged_out_by_user');
        app(AuditLogger::class)->record('session.revoked', $this->user(), null, ['session_id' => $session->getKey(), 'device' => $session->device_label]);

        Notification::make()->title(__('erp.security.device_logged_out', ['device' => $session->device_label]))->success()->send();
    }

    public function logoutOthers(bool $alsoStopIntegrations = false): void
    {
        $count = app(SessionTracker::class)->revokeOthers($this->user(), $this->currentSessionHash(), $this->user(), 'logged_out_others');

        app(AuditLogger::class)->record('session.revoked_others', $this->user(), null, ['count' => $count, 'stop_integrations' => $alsoStopIntegrations]);

        if ($alsoStopIntegrations) {
            McpIntegrationsStopRequested::dispatch($this->user(), 'logged_out_others');
        }

        Notification::make()->title(__('erp.security.others_logged_out', ['count' => $count]))->success()->send();
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
