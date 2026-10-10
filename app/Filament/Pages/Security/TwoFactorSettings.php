<?php

namespace App\Filament\Pages\Security;

use App\Exceptions\Domain\DomainException;
use App\Models\User;
use App\Services\Security\MfaPolicy;
use App\Services\Security\RecoveryCodeService;
use App\Services\Security\TwoFactorService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Settings → Two-factor authentication (spec §3A.2): password step-up, QR code
 * + manual key, code confirmation and one-time recovery codes.
 */
class TwoFactorSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'My Account';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'security/two-factor';

    protected string $view = 'filament.pages.security.two-factor-settings';

    public bool $settingUp = false;

    public ?string $confirmationCode = null;

    /**
     * Plain recovery codes, present only between enabling / regenerating and
     * the user ticking "I have saved my recovery codes".
     *
     * @var list<string>
     */
    public array $recoveryCodes = [];

    public bool $savedRecoveryCodes = false;

    #[Url]
    public ?string $from = null;

    public static function getNavigationLabel(): string
    {
        return __('erp.security.two_factor_nav');
    }

    public function getTitle(): string
    {
        return __('erp.security.two_factor_title');
    }

    public function mount(): void
    {
        $this->settingUp = app(TwoFactorService::class)->pendingSecret($this->user()) !== null;
    }

    public function isEnabled(): bool
    {
        return app(TwoFactorService::class)->isEnabled($this->user());
    }

    public function isRequiredByPolicy(): bool
    {
        return app(MfaPolicy::class)->isRequiredFor($this->user());
    }

    public function setupSecret(): ?string
    {
        return app(TwoFactorService::class)->pendingSecret($this->user());
    }

    public function qrCodeDataUri(): ?string
    {
        $secret = $this->setupSecret();

        if ($secret === null) {
            return null;
        }

        $service = app(TwoFactorService::class);

        return $service->qrCodeDataUri($service->provisioningUri($this->user(), $secret));
    }

    public function remainingRecoveryCodes(): int
    {
        return app(RecoveryCodeService::class)->remaining($this->user());
    }

    public function startSetupAction(): Action
    {
        return Action::make('startSetup')
            ->label(__('erp.security.setup_start'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->modalHeading(__('erp.security.password_step_up'))
            ->modalDescription(__('erp.security.password_step_up_help'))
            ->modalSubmitActionLabel(__('erp.security.continue'))
            ->schema([
                TextInput::make('password')->label(__('erp.security.password'))->password()->required()->autocomplete('current-password'),
            ])
            ->action(function (array $data): void {
                $this->assertPassword((string) $data['password']);

                app(TwoFactorService::class)->beginSetup($this->user());
                $this->settingUp = true;
                $this->confirmationCode = null;
            });
    }

    public function confirmSetup(): void
    {
        $this->resetErrorBag();

        try {
            $this->recoveryCodes = app(TwoFactorService::class)->confirmSetup($this->user(), (string) $this->confirmationCode);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['confirmationCode' => $exception->getMessage()]);
        }

        $this->settingUp = false;
        $this->confirmationCode = null;
        $this->savedRecoveryCodes = false;

        $session = app(\App\Services\Security\SessionTracker::class)->find(request());
        $session?->forceFill(['mfa_passed_at' => now()])->save();

        Notification::make()->title(__('erp.security.enabled_notice'))->success()->send();
    }

    public function cancelSetup(): void
    {
        \App\Models\UserMfa::query()->where('user_id', $this->user()->getKey())->whereNull('enabled_at')->delete();
        $this->settingUp = false;
    }

    public function finishRecoveryCodes(): void
    {
        if (! $this->savedRecoveryCodes) {
            return;
        }

        $this->recoveryCodes = [];
        $this->savedRecoveryCodes = false;

        if ($this->from === 'ai-integrations') {
            $this->redirect(\App\Filament\Pages\Settings\AiIntegrations::getUrl());
        }
    }

    public function regenerateAction(): Action
    {
        return Action::make('regenerate')
            ->label(__('erp.security.regenerate_codes'))
            ->color('gray')
            ->modalHeading(__('erp.security.regenerate_codes'))
            ->modalDescription(__('erp.security.regenerate_help'))
            ->schema([
                TextInput::make('code')->label(__('erp.security.current_code'))->required()->autocomplete('one-time-code'),
            ])
            ->action(function (array $data): void {
                try {
                    $this->recoveryCodes = app(TwoFactorService::class)->regenerateRecoveryCodes($this->user(), (string) $data['code']);
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(['mountedActions.0.data.code' => $exception->getMessage()]);
                }

                $this->savedRecoveryCodes = false;
            });
    }

    public function disableAction(): Action
    {
        return Action::make('disable')
            ->label(__('erp.security.disable'))
            ->color('danger')
            ->modalHeading(__('erp.security.disable'))
            ->modalDescription(__('erp.security.disable_help'))
            ->schema([
                TextInput::make('password')->label(__('erp.security.password'))->password()->required()->autocomplete('current-password'),
                TextInput::make('code')->label(__('erp.security.current_code_or_recovery'))->required()->autocomplete('one-time-code'),
            ])
            ->action(function (array $data): void {
                try {
                    app(TwoFactorService::class)->disable($this->user(), (string) $data['password'], (string) $data['code']);
                } catch (DomainException $exception) {
                    throw ValidationException::withMessages(['mountedActions.0.data.code' => $exception->getMessage()]);
                }

                $this->recoveryCodes = [];
                Notification::make()->title(__('erp.security.disabled_notice'))->success()->send();
            });
    }

    protected function assertPassword(string $password): void
    {
        if (! Hash::check($password, $this->user()->password)) {
            throw ValidationException::withMessages(['mountedActions.0.data.password' => __('erp.security.password_wrong')]);
        }
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
