<?php

use App\Events\TwoFactorDeactivated;
use App\Filament\Pages\Security\TwoFactorSettings;
use App\Models\UserMfa;
use App\Services\Security\RecoveryCodeService;
use App\Services\Security\TwoFactorService;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->freezeTime();
    seedTestDataset();

    $this->user = datasetUser(T::TEACHER);
    $this->actingAs($this->user);
});

it('requires the password before the setup starts', function () {
    Livewire::test(TwoFactorSettings::class)
        ->assertSee('Set up two-factor authentication')
        ->callAction('startSetup', ['password' => 'not-the-password'])
        ->assertHasActionErrors();

    expect(UserMfa::query()->count())->toBe(0);

    Livewire::test(TwoFactorSettings::class)
        ->callAction('startSetup', ['password' => T::PASSWORD])
        ->assertHasNoActionErrors()
        ->assertSet('settingUp', true)
        ->assertSee('Setup key');

    expect(app(TwoFactorService::class)->pendingSecret($this->user))->not->toBeNull()
        ->and(app(TwoFactorService::class)->isEnabled($this->user))->toBeFalse();
});

it('shows the qr code and the manual key, and only enables 2FA after a correct code', function () {
    Livewire::test(TwoFactorSettings::class)->callAction('startSetup', ['password' => T::PASSWORD]);

    $secret = app(TwoFactorService::class)->pendingSecret($this->user);

    $component = Livewire::test(TwoFactorSettings::class)
        ->assertSet('settingUp', true)
        ->assertSeeHtml('data-testid="mfa-qr"')
        ->assertSee($secret)
        ->set('confirmationCode', '000000')
        ->call('confirmSetup')
        ->assertHasErrors(['confirmationCode']);

    expect(app(TwoFactorService::class)->isEnabled($this->user))->toBeFalse();

    $component->set('confirmationCode', totpCode($secret))->call('confirmSetup')->assertHasNoErrors();

    expect(app(TwoFactorService::class)->isEnabled($this->user))->toBeTrue()
        ->and($component->get('recoveryCodes'))->toHaveCount(10);
});

it('shows ten recovery codes after enabling and only lets the user finish once the box is ticked', function () {
    Livewire::test(TwoFactorSettings::class)->callAction('startSetup', ['password' => T::PASSWORD]);
    $secret = app(TwoFactorService::class)->pendingSecret($this->user);

    $component = Livewire::test(TwoFactorSettings::class)
        ->set('confirmationCode', totpCode($secret))
        ->call('confirmSetup')
        ->assertSeeHtml('data-testid="recovery-codes"')
        ->assertSee('I have saved my recovery codes');

    $component->call('finishRecoveryCodes');
    expect($component->get('recoveryCodes'))->toHaveCount(10);

    $component->set('savedRecoveryCodes', true)->call('finishRecoveryCodes');
    expect($component->get('recoveryCodes'))->toBe([]);

    $component->assertSee('Two-factor authentication is on')->assertSee('Unused recovery codes: 10');
});

it('warns when fewer than three recovery codes are left', function () {
    $codes = enableTwoFactorFor($this->user);
    $service = app(RecoveryCodeService::class);

    foreach (array_slice($codes, 0, 7) as $code) {
        $service->consume($this->user, $code);
    }

    Livewire::test(TwoFactorSettings::class)
        ->assertSee('Unused recovery codes: 3')
        ->assertDontSeeHtml('data-testid="recovery-warning"');

    $service->consume($this->user, $codes[7]);

    Livewire::test(TwoFactorSettings::class)
        ->assertSee('Unused recovery codes: 2')
        ->assertSeeHtml('data-testid="recovery-warning"');
});

it('regenerates recovery codes only with a current code and replaces the old set', function () {
    $old = enableTwoFactorFor($this->user);

    Livewire::test(TwoFactorSettings::class)
        ->callAction('regenerate', ['code' => '000000'])
        ->assertHasActionErrors();

    $component = Livewire::test(TwoFactorSettings::class)
        ->callAction('regenerate', ['code' => totpCode('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')])
        ->assertHasNoActionErrors();

    expect($component->get('recoveryCodes'))->toHaveCount(10)
        ->and(array_intersect($old, $component->get('recoveryCodes')))->toBe([]);
});

it('disables 2FA with password and code and announces it so integrations can be stopped', function () {
    Event::fake([TwoFactorDeactivated::class]);
    enableTwoFactorFor($this->user);

    Livewire::test(TwoFactorSettings::class)
        ->callAction('disable', ['password' => 'wrong', 'code' => totpCode('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')])
        ->assertHasActionErrors();

    expect(app(TwoFactorService::class)->isEnabled($this->user))->toBeTrue();
    Event::assertNotDispatched(TwoFactorDeactivated::class);

    Livewire::test(TwoFactorSettings::class)
        ->callAction('disable', ['password' => T::PASSWORD, 'code' => totpCode('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP')])
        ->assertHasNoActionErrors();

    expect(app(TwoFactorService::class)->isEnabled($this->user))->toBeFalse();
    Event::assertDispatched(TwoFactorDeactivated::class, fn (TwoFactorDeactivated $event): bool => $event->user->is($this->user));
});

it('lets the user cancel a pending setup', function () {
    Livewire::test(TwoFactorSettings::class)->callAction('startSetup', ['password' => T::PASSWORD]);

    Livewire::test(TwoFactorSettings::class)->call('cancelSetup')->assertSet('settingUp', false);

    expect(UserMfa::query()->count())->toBe(0);
});
