<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\Security\SessionTracker;
use App\Services\Security\TrustedDeviceService;
use App\Services\Security\TwoFactorResult;
use App\Services\Security\TwoFactorService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Second login step for users with 2FA: authenticator code or recovery code,
 * optionally trusting the device (spec §3A.2).
 *
 * @property-read Schema $form
 */
class TwoFactorChallenge extends SimplePage
{
    public static function getRouteName(): string
    {
        return 'filament.'.filament()->getCurrentOrDefaultPanel()->getId().'.auth.two-factor-challenge';
    }

    public static function getUrl(): string
    {
        return route(static::getRouteName());
    }

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $session = app(SessionTracker::class)->find(request());

        if ($session === null || $session->mfa_passed_at !== null || ! app(TwoFactorService::class)->isEnabled($this->user())) {
            $this->redirect(filament()->getUrl());

            return;
        }

        $this->form->fill();
    }

    public function authenticate(): void
    {
        $data = $this->form->getState();
        $user = $this->user();

        $result = app(TwoFactorService::class)->verify($user, (string) $data['code']);

        if (! $result->isSuccess()) {
            throw ValidationException::withMessages(['data.code' => $this->failureMessage($user, $result)]);
        }

        $session = app(SessionTracker::class)->find(request());
        $session?->forceFill(['mfa_passed_at' => now()])->save();

        if ($session !== null && ! empty($data['trust'])) {
            app(TrustedDeviceService::class)->trust($user, $session);
        }

        $this->redirect(filament()->getUrl());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label(__('erp.security.challenge_code_label'))
                ->helperText(__('erp.security.challenge_code_help'))
                ->required()
                ->autocomplete('one-time-code')
                ->autofocus()
                ->maxLength(20),
            Checkbox::make('trust')
                ->label(__('erp.security.trust_device', ['days' => config('security.two_factor.trust_device_days')]))
                ->visible(fn (): bool => app(TrustedDeviceService::class)->enabled()),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('authenticate')
                ->footer([
                    Actions::make([
                        Action::make('authenticate')
                            ->label(__('erp.security.challenge_submit'))
                            ->submit('authenticate'),
                    ])->fullWidth(),
                ]),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('erp.security.challenge_title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('erp.security.challenge_title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('erp.security.challenge_subheading');
    }

    protected function failureMessage(User $user, TwoFactorResult $result): string
    {
        if ($result === TwoFactorResult::Locked) {
            $minutes = max(1, (int) ceil(app(TwoFactorService::class)->secondsUntilUnlocked($user) / 60));

            return __('erp.security.challenge_locked', ['minutes' => $minutes]);
        }

        return __('erp.security.challenge_invalid');
    }

    protected function user(): User
    {
        $user = Auth::user();
        assert($user instanceof User);

        return $user;
    }
}
