<?php

use App\Models\McpIntegration;
use Database\Seeders\Testing\TestDataset as T;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
});

it('E2E 13: a user without 2FA is blocked, enables 2FA, runs the wizard, connects a read-only client, sees it connected and stops it', function () {
    $page = uiLogin(T::TEACHER);

    $page->navigate('/settings/ai-integrations')
        ->assertSee('AI integrations require two-factor authentication')
        ->assertPresent('[data-testid=mfa-gate]')
        ->assertNotPresent('#start-wizard');

    $page->click('Set up 2FA')->wait(2)->assertPathBeginsWith('/security/two-factor')
        ->click('Set up two-factor authentication')->wait(1)
        ->type('[id="mountedActionSchema0.password"]', T::PASSWORD)->click('Continue')->wait(2)
        ->assertPresent('[data-testid=mfa-qr]');

    $secret = trim((string) $page->text('[data-testid=mfa-secret]'));
    $page->fill('#confirmation-code', totpCode($secret))->click('#confirm-two-factor')->wait(2)
        ->assertPresent('[data-testid=recovery-codes]')
        ->check('#saved-recovery-codes')->wait(1)->click('Done')->wait(3);

    $page->assertPathIs('/settings/ai-integrations')->assertPresent('#start-wizard')->assertSee('No AI clients are connected');

    $page->click('Connect an AI client')->wait(2)->assertSee('Choose your AI client');
    $page->click('Claude Code')->click('#wizard-next')->wait(1);
    $page->assertValue('#integration-name', 'Claude Code – My computer');
    $page->click('#access-read-only')->click('#wizard-next')->wait(1);

    $page->fill('#totp-code', '000000')->click('#wizard-next')->wait(2)->assertPresent('[data-testid=wizard-error]');
    $page->fill('#totp-code', totpCode($secret, 1))->click('#wizard-next')->wait(3);

    $token = trim((string) $page->text('[data-testid=new-token]'));
    expect($token)->toStartWith('erpmcp_');
    $page->assertSee('shown only once')->assertSeeIn('[data-testid=client-snippet]', 'claude mcp add --transport http fec-erp')->assertSeeIn('[data-testid=client-snippet]', $token);

    $page->click('#wizard-next')->wait(1)->assertPresent('[data-testid=waiting]');

    $client = new McpClient($this, $token);
    $tools = collect($client->tools());
    expect($tools->count())->toBeGreaterThan(10)
        ->and($tools->every(fn (array $tool): bool => $tool['annotations']['readOnlyHint'] === true))->toBeTrue()
        ->and($client->errorCode('result_enter_marks', ['enrollment_id' => 1, 'marks' => 50]))->toBe('FORBIDDEN')
        ->and($client->call('me_get_profile')['isError'])->toBeFalse();

    $page->wait(4)->assertPresent('[data-testid=connected]')->assertSee('Connected ✓');

    $page->click('#wizard-close')->wait(2)->assertNotPresent('[data-testid=wizard]');
    $page->assertSeeIn('[data-testid=integrations-table]', 'Claude Code – My computer')->assertSeeIn('[data-testid=integrations-table]', 'Read-only');
    $page->assertSeeIn('[data-testid=last-used]', '127.0.0.1');
    expect((int) $page->text('[data-testid=call-count]'))->toBeGreaterThanOrEqual(2);

    $page->click('Stop')->wait(1)->click('Confirm')->wait(3);
    $page->assertSeeIn('[data-testid=integrations-table]', 'Revoked');

    expect($client->request('tools/list')->assertUnauthorized()->json('error.code'))->toBe('TOKEN_REVOKED');
});

it('E2E 14: super admin finds a user\'s integration, revokes it with a reason, and the user sees the notification', function () {
    $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    ['token' => $token, 'integration' => $integration] = mcpIntegrationFor(datasetUser(T::TEACHER), 'read_only', 90, 'vscode');
    $client = new McpClient($this, $token);
    expect($client->call('me_get_profile')['isError'])->toBeFalse();

    $admin = uiLogin(T::SUPER_ADMIN);
    $admin->navigate('/security/mcp-integrations')->assertSee('AI integrations — all users');
    $admin->fill('#filter-user', T::TEACHER)->wait(2)->assertSee($integration->name)->assertSee('Read-only');

    $admin->click('Revoke')->wait(1)->fill('[id="mountedActionSchema0.reason"]', 'Suspicious activity on this account')->click('Submit')->wait(3);
    $admin->assertSeeIn('[data-testid=all-integrations]', 'Revoked')->assertSee('Suspicious activity on this account');

    expect(McpIntegration::query()->find($integration->id)->isRevoked())->toBeTrue()
        ->and($client->request('tools/list')->assertUnauthorized()->json('error.code'))->toBe('TOKEN_REVOKED');

    $user = uiLogin(T::TEACHER)->assertSee('Two-factor verification');
    $user->fill('input[autocomplete=one-time-code]', totpCode($secret, 1))->click('Verify')->wait(2)->assertPathIs('/');
    $user->click('.fi-topbar-database-notifications-btn')->wait(2)
        ->assertSee('AI integration stopped')
        ->assertSee('Suspicious activity on this account');
});
