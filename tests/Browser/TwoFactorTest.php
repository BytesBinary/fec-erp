<?php

use App\Models\UserMfa;
use App\Notifications\TwoFactorLockedOut;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
});

/**
 * Types an authenticator or recovery code into the challenge form and submits it.
 */
function submitChallengeCode(Pest\Browser\Api\AwaitableWebpage $page, string $code): Pest\Browser\Api\AwaitableWebpage
{
    return $page->fill('input[autocomplete=one-time-code]', $code)->click('Verify')->wait(2);
}

it('E2E 12: sets up 2FA from the page, then logs in with a code and with a one-time recovery code', function () {
    $page = uiLogin(T::TEACHER);

    $page->navigate('/security/two-factor')
        ->assertSee('Two-factor authentication is off')
        ->click('Set up two-factor authentication')
        ->wait(1)
        ->type('[id="mountedActionSchema0.password"]', 'wrong-password')
        ->click('Continue')
        ->wait(2)
        ->assertSee('The password is not correct.')
        ->clear('[id="mountedActionSchema0.password"]')
        ->type('[id="mountedActionSchema0.password"]', T::PASSWORD)
        ->click('Continue')
        ->wait(2)
        ->assertPresent('[data-testid=mfa-qr]');

    $secret = trim((string) $page->text('[data-testid=mfa-secret]'));
    expect($secret)->toHaveLength(32);

    $page->fill('#confirmation-code', '000000')->click('#confirm-two-factor')->wait(2)
        ->assertSee('That code is not correct');

    $page->fill('#confirmation-code', totpCode($secret))->click('#confirm-two-factor')->wait(2)
        ->assertPresent('[data-testid=recovery-codes]')
        ->assertButtonDisabled('Done');

    $codes = preg_split('/\s+/', trim((string) $page->text('[data-testid=recovery-codes]')));
    expect($codes)->toHaveCount(10);

    $page->check('#saved-recovery-codes')->wait(1)->click('Done')->wait(2)
        ->assertSee('Two-factor authentication is on')
        ->assertSee('Unused recovery codes: 10');

    uiLogout($page);

    $login = fn () => uiLogin(T::TEACHER);

    $second = $login();
    $second->assertSee('Two-factor verification');
    submitChallengeCode($second, '000000')->assertSee('That code is not correct');
    submitChallengeCode($second, totpCode($secret, 1))->assertPathIs('/')->assertSee('Dashboard');

    uiLogout($second);

    $third = $login();
    $third->assertSee('Two-factor verification');
    submitChallengeCode($third, $codes[0])->assertPathIs('/')->assertSee('Dashboard');

    uiLogout($third);

    $fourth = $login();
    submitChallengeCode($fourth, $codes[0])->assertSee('That code is not correct')->assertPathIs('/two-factor-challenge');
});

it('E2E 15: five wrong codes lock the challenge and notify the user', function () {
    $user = datasetUser(T::LIBRARIAN);
    $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    enableTwoFactorFor($user, $secret);

    $page = uiLogin(T::LIBRARIAN)->assertSee('Two-factor verification');

    foreach (range(1, 4) as $attempt) {
        submitChallengeCode($page, '111111')->assertSee('That code is not correct');
    }

    submitChallengeCode($page, '111111')->assertSee('Two-factor attempts are locked');

    submitChallengeCode($page, totpCode($secret))->assertSee('Two-factor attempts are locked')->assertPathIs('/two-factor-challenge');

    expect(UserMfa::query()->find($user->id)->isLocked())->toBeTrue()
        ->and($user->notifications()->where('type', TwoFactorLockedOut::class)->count())->toBe(1);
});
