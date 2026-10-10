<?php

use App\Models\MfaRecoveryCode;
use App\Models\UserMfa;
use App\Notifications\TwoFactorLockedOut;
use App\Services\Security\RecoveryCodeService;
use App\Services\Security\TwoFactorResult;
use App\Services\Security\TwoFactorService;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedTestDataset();

    $this->user = datasetUser(T::TEACHER);
    $this->service = app(TwoFactorService::class);
    $this->secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
});

it('stays inactive until a correct code confirms the setup, then returns ten recovery codes', function () {
    $secret = $this->service->beginSetup($this->user);

    expect($this->service->isEnabled($this->user))->toBeFalse()
        ->and($this->service->pendingSecret($this->user))->toBe($secret);

    expect(fn () => $this->service->confirmSetup($this->user, '000000'))->toThrow(App\Exceptions\Domain\ValidationException::class);
    expect($this->service->isEnabled($this->user))->toBeFalse();

    $codes = $this->service->confirmSetup($this->user, totpCode($secret));

    expect($this->service->isEnabled($this->user))->toBeTrue()
        ->and($codes)->toHaveCount(10)
        ->and(app(RecoveryCodeService::class)->remaining($this->user))->toBe(10);
});

it('builds an otpauth uri with the institution as issuer and the email as account', function () {
    $secret = $this->service->beginSetup($this->user);
    $uri = $this->service->provisioningUri($this->user, $secret);

    expect($uri)->toStartWith('otpauth://totp/')
        ->and($uri)->toContain(rawurlencode('FEC Test Institute'))
        ->and($uri)->toContain(rawurlencode($this->user->email))
        ->and($this->service->qrCodeDataUri($uri))->toStartWith('data:image/svg+xml;base64,');
});

it('encrypts the secret at rest and hashes recovery codes', function () {
    $codes = enableTwoFactorFor($this->user, $this->secret);

    $raw = DB::table('user_mfa')->where('user_id', $this->user->id)->value('totp_secret_encrypted');

    expect($raw)->not->toContain($this->secret)
        ->and(Crypt::decryptString($raw))->toBe($this->secret);

    foreach (DB::table('mfa_recovery_codes')->pluck('code_hash') as $hash) {
        expect($codes)->not->toContain($hash)
            ->and(strlen($hash))->toBe(64);
    }
});

it('accepts the current code and one step of clock drift either way', function (int $offset) {
    enableTwoFactorFor($this->user, $this->secret);

    expect($this->service->verify($this->user, totpCode($this->secret, $offset)))->toBe(TwoFactorResult::Valid);
})->with([-1, 0, 1]);

it('rejects codes outside the drift window', function (int $offset) {
    enableTwoFactorFor($this->user, $this->secret);

    expect($this->service->verify($this->user, totpCode($this->secret, $offset)))->toBe(TwoFactorResult::Invalid);
})->with([-3, -2, 2, 3]);

it('rejects non numeric and malformed codes', function (string $input) {
    enableTwoFactorFor($this->user, $this->secret);

    expect($this->service->verify($this->user, $input))->toBe(TwoFactorResult::Invalid);
})->with(['', 'abcdef', '12345', '1234567', '12 34']);

it('never accepts the same time step twice', function () {
    enableTwoFactorFor($this->user, $this->secret);
    $code = totpCode($this->secret);

    expect($this->service->verify($this->user, $code))->toBe(TwoFactorResult::Valid)
        ->and($this->service->verify($this->user, $code))->toBe(TwoFactorResult::Invalid)
        ->and($this->service->verify($this->user, totpCode($this->secret, -1)))->toBe(TwoFactorResult::Invalid)
        ->and($this->service->verify($this->user, totpCode($this->secret, 1)))->toBe(TwoFactorResult::Valid);
});

it('locks attempts for 15 minutes after five wrong codes and notifies the user', function () {
    Notification::fake();
    enableTwoFactorFor($this->user, $this->secret);

    foreach (range(1, 4) as $attempt) {
        expect($this->service->verify($this->user, '000000'))->toBe(TwoFactorResult::Invalid);
    }

    expect($this->service->verify($this->user, '000000'))->toBe(TwoFactorResult::Locked);
    Notification::assertSentTo($this->user, TwoFactorLockedOut::class);

    expect($this->service->verify($this->user, totpCode($this->secret)))->toBe(TwoFactorResult::Locked);

    $this->travel(14)->minutes();
    expect($this->service->verify($this->user, totpCode($this->secret)))->toBe(TwoFactorResult::Locked);

    $this->travel(2)->minutes();
    expect($this->service->verify($this->user, totpCode($this->secret)))->toBe(TwoFactorResult::Valid);
});

it('resets the failure counter after a successful verification', function () {
    enableTwoFactorFor($this->user, $this->secret);

    foreach (range(1, 4) as $attempt) {
        $this->service->verify($this->user, '000000');
    }

    expect($this->service->verify($this->user, totpCode($this->secret)))->toBe(TwoFactorResult::Valid)
        ->and(UserMfa::query()->find($this->user->id)->failed_attempts)->toBe(0);
});

it('lets each recovery code work exactly once', function () {
    $codes = enableTwoFactorFor($this->user, $this->secret);

    expect($this->service->verify($this->user, $codes[0]))->toBe(TwoFactorResult::RecoveryCodeUsed)
        ->and($this->service->verify($this->user, $codes[0]))->toBe(TwoFactorResult::Invalid)
        ->and($this->service->verify($this->user, strtoupper($codes[1])))->toBe(TwoFactorResult::RecoveryCodeUsed)
        ->and(app(RecoveryCodeService::class)->remaining($this->user))->toBe(8);
});

it('does not let one user spend another user\'s recovery code', function () {
    $codes = enableTwoFactorFor($this->user, $this->secret);
    $other = datasetUser(T::LIBRARIAN);
    enableTwoFactorFor($other, $this->secret);

    expect($this->service->verify($other, $codes[0]))->toBe(TwoFactorResult::Invalid)
        ->and($this->service->verify($this->user, $codes[0]))->toBe(TwoFactorResult::RecoveryCodeUsed);
});

it('invalidates the old recovery codes when new ones are generated and requires a current code', function () {
    $old = enableTwoFactorFor($this->user, $this->secret);

    expect(fn () => $this->service->regenerateRecoveryCodes($this->user, '000000'))->toThrow(App\Exceptions\Domain\ValidationException::class);

    $new = $this->service->regenerateRecoveryCodes($this->user, totpCode($this->secret));

    expect($new)->toHaveCount(10)
        ->and(array_intersect($old, $new))->toBe([])
        ->and($this->service->verify($this->user, $old[0]))->toBe(TwoFactorResult::Invalid)
        ->and($this->service->verify($this->user, $new[0]))->toBe(TwoFactorResult::RecoveryCodeUsed);
});

it('needs the password and a current code to disable 2FA', function () {
    enableTwoFactorFor($this->user, $this->secret);

    expect(fn () => $this->service->disable($this->user, 'wrong-password', totpCode($this->secret)))
        ->toThrow(App\Exceptions\Domain\ValidationException::class)
        ->and($this->service->isEnabled($this->user))->toBeTrue();

    expect(fn () => $this->service->disable($this->user, T::PASSWORD, '000000'))
        ->toThrow(App\Exceptions\Domain\ValidationException::class);

    $this->service->disable($this->user, T::PASSWORD, totpCode($this->secret));

    expect($this->service->isEnabled($this->user))->toBeFalse()
        ->and(MfaRecoveryCode::query()->where('user_id', $this->user->id)->count())->toBe(0);
});

it('can disable 2FA with a recovery code instead of an authenticator code', function () {
    $codes = enableTwoFactorFor($this->user, $this->secret);

    $this->service->disable($this->user, T::PASSWORD, $codes[0]);

    expect($this->service->isEnabled($this->user))->toBeFalse();
});

it('refuses to start a second setup while 2FA is already on', function () {
    enableTwoFactorFor($this->user, $this->secret);

    expect(fn () => $this->service->beginSetup($this->user))->toThrow(App\Exceptions\Domain\InvalidStateException::class);
});

it('reports not enabled for users without 2FA', function () {
    expect($this->service->verify($this->user, '123456'))->toBe(TwoFactorResult::NotEnabled);
});
