<?php

namespace App\Services\Security;

use App\Events\TwoFactorDeactivated;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\ValidationException;
use App\Models\InstitutionSetting;
use App\Models\User;
use App\Models\UserMfa;
use App\Notifications\TwoFactorLockedOut;
use App\Notifications\TwoFactorReset;
use App\Services\Audit\AuditLogger;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP two-factor authentication (RFC 6238) on top of pragmarx/google2fa
 * (spec §3A.2). The secret is encrypted at rest, a used time step can never be
 * accepted twice, and five wrong codes lock attempts for 15 minutes.
 */
class TwoFactorService
{
    public function __construct(
        protected Google2FA $totp,
        protected RecoveryCodeService $recoveryCodes,
        protected SessionTracker $sessions,
        protected TrustedDeviceService $trust,
        protected AuditLogger $audit,
    ) {}

    public function isEnabled(User $user): bool
    {
        return $this->state($user)?->isEnabled() === true;
    }

    public function state(User $user): ?UserMfa
    {
        return UserMfa::query()->find($user->getKey());
    }

    /**
     * Starts (or restarts) setup: stores a fresh pending secret that stays
     * inactive until {@see self::confirmSetup()} sees a correct code.
     *
     * @return string the plain base32 secret, to show as manual setup key
     */
    public function beginSetup(User $user): string
    {
        if ($this->isEnabled($user)) {
            throw new InvalidStateException('Two-factor authentication is already enabled.');
        }

        $secret = $this->totp->generateSecretKey(32);

        UserMfa::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['totp_secret_encrypted' => $secret, 'enabled_at' => null, 'last_used_step' => null, 'failed_attempts' => 0, 'locked_until' => null],
        );

        return $secret;
    }

    public function pendingSecret(User $user): ?string
    {
        $state = $this->state($user);

        return $state !== null && ! $state->isEnabled() ? $state->totp_secret_encrypted : null;
    }

    public function provisioningUri(User $user, string $secret): string
    {
        $issuer = config('security.two_factor.issuer') ?: (InstitutionSetting::current()->institution_name ?: config('app.name'));

        return $this->totp->getQRCodeUrl((string) $issuer, $user->email, $secret);
    }

    /**
     * Renders the provisioning URI as an inline SVG data URI.
     */
    public function qrCodeDataUri(string $uri): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgViewBoxSize' => 200,
        ]);

        return (new QRCode($options))->render($uri);
    }

    /**
     * Activates 2FA after the user proves their authenticator works.
     *
     * @return list<string> the plain recovery codes (shown once)
     */
    public function confirmSetup(User $user, string $code): array
    {
        $state = $this->state($user);

        if ($state === null || $state->isEnabled()) {
            throw new InvalidStateException('There is no two-factor setup waiting for confirmation.');
        }

        $this->assertNotLocked($state);

        $step = $this->matchingStep($state, $code);

        if ($step === null) {
            $this->registerFailure($user, $state);

            throw new ValidationException('That code is not correct. Check the code in your authenticator app and try again.');
        }

        return DB::transaction(function () use ($user, $state, $step): array {
            $state->forceFill(['enabled_at' => now(), 'last_used_step' => $step, 'failed_attempts' => 0, 'locked_until' => null])->save();

            $this->audit->record('two_factor.enabled', $user, null, null, $user);
            app(\App\Services\Notifications\NotificationEvents::class)->emit('security.two_factor_enabled', ['time' => now()->format('d M Y H:i'), 'link' => \App\Filament\Pages\Security\TwoFactorSettings::getUrl()], 'tf_enabled:'.$user->getKey().':'.$step, $user);

            return $this->recoveryCodes->generate($user);
        });
    }

    /**
     * Checks a login / step-up code, or a recovery code. Wrong attempts count
     * toward the lockout; a spent time step is rejected.
     */
    public function verify(User $user, string $input): TwoFactorResult
    {
        $state = $this->state($user);

        if ($state === null || ! $state->isEnabled()) {
            return TwoFactorResult::NotEnabled;
        }

        if ($state->isLocked()) {
            return TwoFactorResult::Locked;
        }

        $input = trim($input);

        if ($this->recoveryCodes->looksLikeRecoveryCode($input)) {
            if ($this->recoveryCodes->consume($user, $input)) {
                $this->clearFailures($state);
                $this->audit->record('two_factor.recovery_code_used', $user, null, null, $user);
                app(\App\Services\Notifications\NotificationEvents::class)->emit('security.recovery_code_used', ['time' => now()->format('d M Y H:i'), 'codes_left' => $this->recoveryCodes->remaining($user), 'link' => \App\Filament\Pages\Security\TwoFactorSettings::getUrl()], null, $user);

                return TwoFactorResult::RecoveryCodeUsed;
            }
        } else {
            $step = $this->matchingStep($state, $input);

            if ($step !== null) {
                $claimed = UserMfa::query()
                    ->where('user_id', $user->getKey())
                    ->where(fn ($query) => $query->whereNull('last_used_step')->orWhere('last_used_step', '<', $step))
                    ->update(['last_used_step' => $step, 'failed_attempts' => 0, 'locked_until' => null]);

                if ($claimed === 1) {
                    return TwoFactorResult::Valid;
                }
            }
        }

        return $this->registerFailure($user, $state->refresh());
    }

    /**
     * Turns 2FA off. Needs the account password and a current code (or a
     * recovery code). Revokes the user's trusted devices.
     */
    public function disable(User $user, string $password, string $code): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new ValidationException('The password is not correct.');
        }

        $result = $this->verify($user, $code);

        if (! $result->isSuccess()) {
            throw new ValidationException($result === TwoFactorResult::Locked
                ? 'Too many wrong codes. Try again in a few minutes.'
                : 'That code is not correct.');
        }

        $this->deactivate($user, 'two_factor.disabled', $user);
        app(\App\Services\Notifications\NotificationEvents::class)->emit('security.two_factor_disabled', ['time' => now()->format('d M Y H:i'), 'link' => \App\Filament\Pages\Security\TwoFactorSettings::getUrl()], null, $user);
    }

    /**
     * Super admin reset for a lost phone: removes 2FA, signs the user out
     * everywhere and records why.
     */
    public function adminReset(User $user, User $admin, string $reason): void
    {
        if (trim($reason) === '') {
            throw new ValidationException('A reason is required to reset two-factor authentication.');
        }

        $this->deactivate($user, 'two_factor.reset_by_admin', $admin, ['reason' => $reason]);
        $this->sessions->revokeAll($user, $admin, 'two_factor_reset');

        $user->notify(new TwoFactorReset($reason));
    }

    /**
     * Replaces the recovery codes; needs a current authenticator code.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user, string $code): array
    {
        $result = $this->verify($user, $code);

        if ($result !== TwoFactorResult::Valid) {
            throw new ValidationException('Enter a current code from your authenticator app to generate new recovery codes.');
        }

        $codes = $this->recoveryCodes->generate($user);
        $this->audit->record('two_factor.recovery_codes_regenerated', $user, null, null, $user);
        app(\App\Services\Notifications\NotificationEvents::class)->emit('security.recovery_codes_regenerated', ['time' => now()->format('d M Y H:i'), 'link' => \App\Filament\Pages\Security\TwoFactorSettings::getUrl()], null, $user);

        return $codes;
    }

    public function secondsUntilUnlocked(User $user): int
    {
        $state = $this->state($user);

        return $state?->isLocked() ? (int) now()->diffInSeconds($state->locked_until, true) : 0;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function deactivate(User $user, string $action, User $actor, array $context = []): void
    {
        DB::transaction(function () use ($user, $action, $actor, $context): void {
            UserMfa::query()->where('user_id', $user->getKey())->delete();
            $this->recoveryCodes->purge($user);
            $this->trust->revokeAllFor($user);
            $this->audit->record($action, $user, null, $context ?: null, $actor);
        });

        TwoFactorDeactivated::dispatch($user, $action);
    }

    protected function matchingStep(UserMfa $state, string $code): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{'.config('security.two_factor.digits').'}$/', $code)) {
            return null;
        }

        $step = $this->totp->verifyKeyNewer(
            $state->totp_secret_encrypted,
            $code,
            $state->last_used_step ?? 0,
            (int) config('security.two_factor.window'),
            $this->currentStep(),
        );

        return is_int($step) ? $step : null;
    }

    protected function currentStep(): int
    {
        return intdiv(now()->getTimestamp(), $this->totp->getKeyRegeneration());
    }

    protected function registerFailure(User $user, UserMfa $state): TwoFactorResult
    {
        $attempts = $state->failed_attempts + 1;

        if ($attempts >= (int) config('security.two_factor.max_attempts')) {
            $state->forceFill([
                'failed_attempts' => 0,
                'locked_until' => now()->addMinutes((int) config('security.two_factor.lockout_minutes')),
            ])->save();

            $this->audit->record('two_factor.locked_out', $user, null, null, $user);
            $user->notify(new TwoFactorLockedOut((int) config('security.two_factor.lockout_minutes')));

            return TwoFactorResult::Locked;
        }

        $state->forceFill(['failed_attempts' => $attempts])->save();

        return TwoFactorResult::Invalid;
    }

    protected function clearFailures(UserMfa $state): void
    {
        $state->forceFill(['failed_attempts' => 0, 'locked_until' => null])->save();
    }

    protected function assertNotLocked(UserMfa $state): void
    {
        if ($state->isLocked()) {
            throw new ValidationException('Too many wrong codes. Try again in a few minutes.');
        }
    }
}
