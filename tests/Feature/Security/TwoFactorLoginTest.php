<?php

use App\Filament\Pages\Auth\TwoFactorChallenge;
use App\Filament\Pages\Security\TwoFactorPolicy;
use App\Filament\Pages\Security\TwoFactorSettings;
use App\Models\KnownDevice;
use App\Models\User;
use App\Models\UserMfa;
use App\Models\UserSession;
use App\Notifications\TwoFactorLockedOut;
use App\Services\Security\MfaPolicy;
use App\Services\Security\SessionTracker;
use App\Services\Security\TrustedDeviceService;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

const SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

/**
 * Sends a request as `$user` in the browser session `$sessionId`.
 */
function mfaRequest(User $user, string $sessionId): Tests\TestCase
{
    return test()->flushSession()->actingAs($user)->withCredentials()->withCookie(config('session.cookie'), $sessionId);
}

/**
 * Pins the app's session store to `$sessionId` and creates its tracked record,
 * so Livewire component tests run inside a known browser session.
 */
function livewireSession(User $user, string $sessionId): UserSession
{
    session()->setId($sessionId);
    session()->start();

    return UserSession::query()->create([
        'user_id' => $user->id,
        'session_hash' => hash('sha256', $sessionId),
        'device_label' => 'Chrome on Linux',
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'Linux',
        'ip' => '127.0.0.1',
        'last_active_at' => now(),
        'expires_at' => now()->addHours(12),
    ]);
}

beforeEach(function () {
    seedTestDataset();

    $this->user = datasetUser(T::SUPER_ADMIN);
    $this->recoveryCodes = enableTwoFactorFor($this->user, SECRET);
    $this->sessionId = str_repeat('m', 40);
});

it('sends a user with 2FA to the challenge until the code is entered', function () {
    mfaRequest($this->user, $this->sessionId)->get('/')->assertRedirect(TwoFactorChallenge::getUrl());
    mfaRequest($this->user, $this->sessionId)->get(TwoFactorSettings::getUrl())->assertRedirect(TwoFactorChallenge::getUrl());
    mfaRequest($this->user, $this->sessionId)->get(TwoFactorChallenge::getUrl())->assertOk()->assertSee('Two-factor verification');
});

it('answers json requests that skipped the challenge with 403 MFA_CHALLENGE_REQUIRED', function () {
    mfaRequest($this->user, $this->sessionId)->getJson('/')->assertForbidden()->assertJsonPath('error.code', 'MFA_CHALLENGE_REQUIRED');
});

it('lets the user through once the session passed the challenge', function () {
    mfaRequest($this->user, $this->sessionId)->get('/');
    UserSession::query()->firstOrFail()->forceFill(['mfa_passed_at' => now()])->save();

    mfaRequest($this->user, $this->sessionId)->get('/')->assertOk();
});

it('does not challenge users without 2FA', function () {
    mfaRequest(datasetUser(T::LIBRARIAN), $this->sessionId)->get('/')->assertOk();
});

it('accepts a correct authenticator code, rejects a wrong one, and never lets a spent code in again', function () {
    $record = livewireSession($this->user, $this->sessionId);

    $challenge = fn () => Livewire::actingAs($this->user)->test(TwoFactorChallenge::class);

    $challenge()->fillForm(['code' => '000000'])->call('authenticate')->assertHasFormErrors(['code']);
    expect($record->fresh()->mfa_passed_at)->toBeNull();

    $challenge()->fillForm(['code' => totpCode(SECRET)])->call('authenticate')->assertHasNoFormErrors()->assertRedirect(filament()->getUrl());
    expect($record->fresh()->mfa_passed_at)->not->toBeNull();

    $record->forceFill(['mfa_passed_at' => null])->save();
    $challenge()->fillForm(['code' => totpCode(SECRET)])->call('authenticate')->assertHasFormErrors(['code']);
    expect($record->fresh()->mfa_passed_at)->toBeNull();
});

it('accepts a recovery code once and rejects it the second time', function () {
    $record = livewireSession($this->user, $this->sessionId);
    $challenge = fn () => Livewire::actingAs($this->user)->test(TwoFactorChallenge::class);

    $challenge()->fillForm(['code' => $this->recoveryCodes[0]])->call('authenticate')->assertHasNoFormErrors();
    expect($record->fresh()->mfa_passed_at)->not->toBeNull();

    $record->forceFill(['mfa_passed_at' => null])->save();
    $challenge()->fillForm(['code' => $this->recoveryCodes[0]])->call('authenticate')->assertHasFormErrors(['code']);
});

it('locks the challenge after five wrong codes and notifies the user', function () {
    Notification::fake();
    livewireSession($this->user, $this->sessionId);
    $challenge = fn () => Livewire::actingAs($this->user)->test(TwoFactorChallenge::class);

    foreach (range(1, 5) as $attempt) {
        $challenge()->fillForm(['code' => '111111'])->call('authenticate')->assertHasFormErrors(['code']);
    }

    Notification::assertSentTo($this->user, TwoFactorLockedOut::class);

    $challenge()->fillForm(['code' => totpCode(SECRET)])->call('authenticate')->assertHasFormErrors(['code']);
    expect(UserSession::query()->firstOrFail()->mfa_passed_at)->toBeNull()
        ->and(UserMfa::query()->find($this->user->id)->isLocked())->toBeTrue();
});

it('skips the challenge in a browser that holds a valid trust cookie and drops trust when that session is logged out', function () {
    $record = livewireSession($this->user, $this->sessionId);

    $challenge = Livewire::actingAs($this->user)->test(TwoFactorChallenge::class);
    $challenge->fillForm(['code' => totpCode(SECRET), 'trust' => true])->call('authenticate')->assertHasNoFormErrors();

    $record->refresh();
    expect($record->isTrusted())->toBeTrue()
        ->and(KnownDevice::query()->where('user_id', $this->user->id)->whereNotNull('trusted_token_hash')->count())->toBe(1);

    $token = 'trust-token-for-test';
    KnownDevice::query()->where('user_id', $this->user->id)->update(['trusted_token_hash' => hash('sha256', $token), 'trusted_until' => now()->addDays(30)]);

    test()->flushSession()->actingAs($this->user)->withCredentials()
        ->withCookie(config('session.cookie'), str_repeat('n', 40))
        ->withCookie(config('security.two_factor.trust_cookie'), $token)
        ->get('/')->assertOk();

    $inherited = UserSession::query()->where('session_hash', hash('sha256', str_repeat('n', 40)))->firstOrFail();
    expect($inherited->mfa_passed_at)->not->toBeNull()->and($inherited->isTrusted())->toBeTrue();

    app(SessionTracker::class)->revoke($inherited, $this->user, 'test');

    test()->flushSession()->actingAs($this->user)->withCredentials()
        ->withCookie(config('session.cookie'), str_repeat('o', 40))
        ->withCookie(config('security.two_factor.trust_cookie'), $token)
        ->get('/')->assertRedirect(TwoFactorChallenge::getUrl());
});

it('ignores an expired or unknown trust cookie', function () {
    mfaRequest($this->user, $this->sessionId)->get('/');
    KnownDevice::query()->where('user_id', $this->user->id)->update(['trusted_token_hash' => hash('sha256', 'old'), 'trusted_until' => now()->subDay()]);

    test()->flushSession()->actingAs($this->user)->withCredentials()
        ->withCookie(config('session.cookie'), str_repeat('p', 40))
        ->withCookie(config('security.two_factor.trust_cookie'), 'old')
        ->get('/')->assertRedirect(TwoFactorChallenge::getUrl());
});

it('does not offer trust when the feature is switched off', function () {
    config(['security.two_factor.trust_device_enabled' => false]);
    mfaRequest($this->user, $this->sessionId)->get('/');
    $record = UserSession::query()->firstOrFail();

    app(TrustedDeviceService::class)->trust($this->user, $record);

    expect($record->fresh()->isTrusted())->toBeFalse();
});

it('forces users of a role with mandatory 2FA through setup and releases them afterwards', function () {
    $librarian = datasetUser(T::LIBRARIAN);
    app(MfaPolicy::class)->setRoleRequired(Role::findByName('librarian', 'web'), true);

    mfaRequest($librarian, str_repeat('q', 40))->get('/')->assertRedirect(TwoFactorSettings::getUrl());
    mfaRequest($librarian, str_repeat('q', 40))->getJson('/')->assertForbidden()->assertJsonPath('error.code', 'MFA_SETUP_REQUIRED');
    mfaRequest($librarian, str_repeat('q', 40))->get(TwoFactorSettings::getUrl())->assertOk();

    mfaRequest(datasetUser(T::TEACHER), str_repeat('r', 40))->get('/')->assertOk();

    enableTwoFactorFor($librarian, SECRET);
    mfaRequest($librarian, str_repeat('q', 40))->get('/')->assertRedirect(TwoFactorChallenge::getUrl());
});

it('keeps 2FA optional for every role by default', function () {
    foreach (Role::query()->where('guard_name', 'web')->get() as $role) {
        expect(app(MfaPolicy::class)->isRoleRequired($role))->toBeFalse();
    }
});

it('lets only super admin open and change the role policy', function () {
    mfaRequest(datasetUser(T::TEACHER), str_repeat('s', 40))->get(TwoFactorPolicy::getUrl())->assertForbidden();
    mfaRequest(datasetUser(T::SUPER_ADMIN), str_repeat('t', 40))->get('/');
    UserSession::query()->where('user_id', datasetUser(T::SUPER_ADMIN)->id)->update(['mfa_passed_at' => now()]);

    $role = Role::findByName('teacher', 'web');
    Livewire::actingAs(datasetUser(T::SUPER_ADMIN))->test(TwoFactorPolicy::class)->call('toggleRole', $role->id);

    expect(app(MfaPolicy::class)->isRoleRequired($role))->toBeTrue();
});
