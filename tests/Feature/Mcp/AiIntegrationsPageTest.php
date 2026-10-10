<?php

use App\Filament\Pages\Security\Devices;
use App\Filament\Pages\Security\TwoFactorSettings;
use App\Filament\Pages\Settings\AiIntegrations;
use App\Models\McpIntegration;
use App\Models\McpSetting;
use App\Services\Mcp\ClientSnippets;
use App\Services\Mcp\IntegrationService;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;
use Tests\Mcp\McpClient;

const WIZARD_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

beforeEach(function () {
    seedTestDataset();
    $this->user = datasetUser(T::TEACHER);
    $this->actingAs($this->user);
});

/**
 * Walks the wizard through the form fields up to the code step.
 */
function wizardTo(\Livewire\Features\SupportTesting\Testable $component, string $client = 'claude_code', string $access = 'read_only', int $days = 90): \Livewire\Features\SupportTesting\Testable
{
    return $component->call('startWizard')->call('chooseClient', $client)->call('next')
        ->set('accessLevel', $access)->set('expiresInDays', $days)->call('next');
}

it('blocks users without 2FA with an explanation and a set up button that returns here', function () {
    Livewire::test(AiIntegrations::class)
        ->assertSee('AI integrations require two-factor authentication')
        ->assertSeeHtml('data-testid="mfa-gate"')
        ->assertDontSeeHtml('id="start-wizard"')
        ->assertSeeHtml(e(TwoFactorSettings::getUrl(['from' => 'ai-integrations'])));
});

it('sends the user back to AI integrations after finishing 2FA setup that started there', function () {
    Livewire::test(TwoFactorSettings::class, ['from' => 'ai-integrations'])
        ->callAction('startSetup', ['password' => T::PASSWORD]);

    $secret = app(App\Services\Security\TwoFactorService::class)->pendingSecret($this->user);

    Livewire::test(TwoFactorSettings::class, ['from' => 'ai-integrations'])
        ->set('confirmationCode', totpCode($secret))->call('confirmSetup')
        ->set('savedRecoveryCodes', true)->call('finishRecoveryCodes')
        ->assertRedirect(AiIntegrations::getUrl());
});

it('refuses to create an integration without 2FA even if the wizard is driven directly', function () {
    $component = Livewire::test(AiIntegrations::class);
    $component->set('integrationName', 'x')->set('totpCode', '123456')->call('createIntegration');

    expect(McpIntegration::query()->count())->toBe(0);
});

describe('with 2FA', function () {
    beforeEach(function () {
        enableTwoFactorFor($this->user, WIZARD_SECRET);
    });

    it('creates a read-only Claude Code integration through the five steps and shows the token once with a filled-in snippet', function () {
        $component = wizardTo(Livewire::test(AiIntegrations::class))
            ->assertSet('step', 3)
            ->set('totpCode', totpCode(WIZARD_SECRET, 1))
            ->call('createIntegration')
            ->assertSet('step', 4)
            ->assertSeeHtml('data-testid="new-token"')
            ->assertSee('shown only once');

        $integration = McpIntegration::query()->firstOrFail();
        $token = $component->get('newToken');

        expect($integration->access_level)->toBe('read_only')
            ->and($integration->client_type)->toBe('claude_code')
            ->and($integration->token_hash)->toBe(hash('sha256', $token))
            ->and($component->html())->toContain($token)
            ->and($component->html())->toContain('claude mcp add --transport http fec-erp')
            ->and($component->html())->toContain(url('/mcp'));

        $component->call('next')->assertSet('step', 5)->assertSee('Waiting for your AI client');
        $component->call('closeWizard')->assertSet('newToken', null)->assertDontSee($token);
    });

    it('rejects a wrong or reused code and shows the error in the wizard', function () {
        $component = wizardTo(Livewire::test(AiIntegrations::class))->set('totpCode', '000000')->call('createIntegration');

        $component->assertSet('step', 3)->assertSeeHtml('data-testid="wizard-error"')->assertSee('current 6-digit code');
        expect(McpIntegration::query()->count())->toBe(0);
    });

    it('requires a name', function () {
        Livewire::test(AiIntegrations::class)->call('startWizard')->call('next')->set('integrationName', '  ')->call('next')
            ->assertSet('step', 2)->assertSee('Give the integration a name');
    });

    it('pre-fills the name from the chosen client', function () {
        Livewire::test(AiIntegrations::class)->call('startWizard')->call('chooseClient', 'cursor')->assertSet('integrationName', 'Cursor – My computer');
    });

    it('offers never-expire only when super admin allowed it', function () {
        expect(Livewire::test(AiIntegrations::class)->instance()->expiryOptions())->not->toHaveKey(0);

        McpSetting::current()->update(['allow_never_expire' => true]);

        expect(Livewire::test(AiIntegrations::class)->instance()->expiryOptions())->toHaveKey(0);
    });

    it('shows Connected after the first MCP request and troubleshooting after two minutes of silence', function () {
        $component = wizardTo(Livewire::test(AiIntegrations::class), access: 'full')->set('totpCode', totpCode(WIZARD_SECRET, 1))->call('createIntegration')->call('next');

        $component->call('checkConnection')->assertSeeHtml('data-testid="waiting"')->assertDontSeeHtml('data-testid="troubleshooting"');

        $this->travel(121)->seconds();
        $component->call('checkConnection')->assertSeeHtml('data-testid="troubleshooting"');

        (new McpClient($this, $component->get('newToken')))->request('tools/list')->assertOk();

        $component->call('checkConnection')->assertSeeHtml('data-testid="connected"')->assertSee('Connected ✓');
    });

    it('lists integrations with last used, call count and status, and lets the owner stop, rename and review activity', function () {
        ['integration' => $integration, 'token' => $token] = mcpIntegrationFor($this->user, 'full', 90, 'cursor');
        $client = new McpClient($this, $token);
        $client->call('me_get_profile');
        $client->call('user_create', []);
        $this->actingAs($this->user);

        $page = Livewire::test(AiIntegrations::class)
            ->assertSee($integration->name)->assertSee('Cursor')->assertSee('Full access')->assertSee('Active')->assertSee('127.0.0.1');

        expect(app(IntegrationService::class)->callsInLastDays($integration->fresh()))->toBe(2)
            ->and($page->html())->toContain('data-testid="call-count">2<');

        $page->call('showActivity', $integration->id)->assertSee('me_get_profile')->assertSee('denied')->call('hideActivity');

        $page->callAction('rename', ['name' => 'Renamed client'], ['integration' => $integration->id])->assertSee('Renamed client');
        expect($integration->fresh()->name)->toBe('Renamed client');

        $page->callAction('stop', arguments: ['integration' => $integration->id]);
        expect($integration->fresh()->isRevoked())->toBeTrue();
        $client->request('tools/list')->assertUnauthorized()->assertJsonPath('error.code', 'TOKEN_REVOKED');

        Livewire::test(AiIntegrations::class)->assertSee('Revoked');
    });

    it('stops all integrations at once', function () {
        mcpIntegrationFor($this->user);
        mcpIntegrationFor($this->user);
        $this->actingAs($this->user);

        Livewire::test(AiIntegrations::class)->callAction('stopAll');

        expect(McpIntegration::query()->active()->count())->toBe(0);
    });

    it('does not let one user stop or rename another user\'s integration through the page', function () {
        ['integration' => $other] = mcpIntegrationFor(datasetUser(T::LIBRARIAN));
        $this->actingAs($this->user);

        expect(fn () => Livewire::test(AiIntegrations::class)->callAction('stop', arguments: ['integration' => $other->id]))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
        expect($other->fresh()->isRevoked())->toBeFalse();
    });

    it('shows the limit message instead of creating a sixth integration', function () {
        foreach (range(1, 5) as $n) {
            mcpIntegrationFor($this->user);
        }
        $this->actingAs($this->user);

        wizardTo(Livewire::test(AiIntegrations::class))->set('totpCode', totpCode(WIZARD_SECRET, 2))->call('createIntegration')->assertSee('already have 5 active');
    });

    it('stops integrations when the user logs out all other devices with the option ticked', function () {
        ['integration' => $integration] = mcpIntegrationFor($this->user);
        $this->actingAs($this->user);

        Livewire::test(Devices::class)->callAction('logoutOthers', ['stop_integrations' => true]);

        expect($integration->fresh()->isRevoked())->toBeTrue();
    });
});

it('tells the user when an AI client uses the integration from a new IP', function () {
    enableTwoFactorFor($this->user, WIZARD_SECRET);
    ['token' => $token] = mcpIntegrationFor($this->user);
    $client = new McpClient($this, $token);

    $client->request('tools/list')->assertOk();
    expect($this->user->notifications()->get()->pluck('data.title')->all())->not->toContain('AI integration used from a new IP');

    $this->flushSession()->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']);
    $client->request('tools/list')->assertOk();

    expect($this->user->notifications()->get()->pluck('data.title')->all())->toContain('AI integration used from a new IP');
});

it('fills the connection snippet of every client with the server url and token', function (string $client) {
    $snippet = app(ClientSnippets::class)->for($client, 'erpmcp_TESTTOKEN');

    expect($snippet['snippet'])->toContain(url('/mcp'))->toContain('erpmcp_TESTTOKEN')->not->toContain('{token}')->not->toContain('{server_url}')
        ->and($snippet['steps'])->not->toBeEmpty()
        ->and($snippet['file'])->not->toBeEmpty();
})->with(['claude_desktop', 'claude_code', 'cursor', 'vscode', 'other']);

it('hides AI integrations from users whose role is switched off', function () {
    McpSetting::current()->update(['global_enabled' => false]);

    expect(AiIntegrations::canAccess())->toBeFalse();
});
