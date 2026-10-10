<?php

use App\Models\McpIntegration;
use App\Models\McpRoleAccess;
use App\Models\McpSetting;
use App\Models\UserMfa;
use App\Services\Mcp\IntegrationService;
use App\Services\Mcp\McpSettingsService;
use App\Services\Security\TwoFactorService;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
    $this->user = datasetUser(T::TEACHER);
    ['token' => $this->token, 'integration' => $this->integration] = mcpIntegrationFor($this->user);
    $this->client = new McpClient($this, $this->token);
});

function expectUnauthorized(McpClient $client, string $code): void
{
    $response = $client->request('tools/list');

    $response->assertUnauthorized()->assertJsonPath('error.code', $code);
}

it('accepts a valid token', function () {
    $this->client->request('tools/list')->assertOk();
    expect($this->integration->fresh()->first_connected_at)->not->toBeNull()
        ->and($this->integration->fresh()->last_used_at)->not->toBeNull();
});

it('rejects a missing or unknown token', function () {
    expectUnauthorized(new McpClient($this, null), 'TOKEN_MISSING');
    expectUnauthorized(new McpClient($this, 'erpmcp_not-a-real-token'), 'TOKEN_INVALID');
});

it('never stores the plain token', function () {
    $row = DB::table('mcp_integrations')->first();

    expect($row->token_hash)->toBe(hash('sha256', $this->token))
        ->and(json_encode($row))->not->toContain($this->token)
        ->and($row->token_prefix)->toBe(substr($this->token, 0, 12));
});

it('answers TOKEN_REVOKED on the very next request after the integration is stopped', function () {
    $this->client->request('tools/list')->assertOk();

    app(IntegrationService::class)->revoke($this->user, $this->integration);

    expectUnauthorized($this->client, 'TOKEN_REVOKED');
});

it('answers TOKEN_EXPIRED once the expiry date passes', function () {
    $this->client->request('tools/list')->assertOk();

    $this->travel(91)->days();

    expectUnauthorized($this->client, 'TOKEN_EXPIRED');
});

it('answers MFA_REQUIRED the moment the user turns 2FA off', function () {
    $this->client->request('tools/list')->assertOk();

    UserMfa::query()->where('user_id', $this->user->id)->delete();

    expectUnauthorized($this->client, 'MFA_REQUIRED');
});

it('answers MCP_DISABLED when MCP is switched off globally or for every role of the user', function () {
    McpSetting::current()->update(['global_enabled' => false]);
    expectUnauthorized($this->client, 'MCP_DISABLED');

    McpSetting::current()->update(['global_enabled' => true]);
    $this->client->request('tools/list')->assertOk();

    McpRoleAccess::query()->create(['role_id' => Role::findByName('teacher', 'web')->id, 'enabled' => false]);
    expectUnauthorized($this->client, 'MCP_DISABLED');
});

it('keeps MCP on for a user with one enabled role', function () {
    $head = datasetUser(T::DEPT_HEAD_CSE);
    ['token' => $token] = mcpIntegrationFor($head);

    McpRoleAccess::query()->create(['role_id' => Role::findByName('teacher', 'web')->id, 'enabled' => false]);

    (new McpClient($this, $token))->request('tools/list')->assertOk();
});

it('rejects an inactive account', function () {
    $this->user->forceFill(['is_active' => false])->save();

    expectUnauthorized($this->client, 'ACCOUNT_INACTIVE');
});

it('runs every call as the real user and never as a superuser', function () {
    expect($this->client->errorCode('user_list'))->toBe('FORBIDDEN');

    $result = $this->client->call('me_get_profile');

    expect($result['payload']['data']['email'])->toBe(T::TEACHER);
});

it('stops all of a user\'s integrations when 2FA is disabled or reset', function () {
    ['integration' => $second] = mcpIntegrationFor($this->user, 'read_only');

    app(TwoFactorService::class)->adminReset($this->user, datasetUser(T::SUPER_ADMIN), 'Lost phone');

    expect($this->integration->fresh()->isRevoked())->toBeTrue()
        ->and($second->fresh()->isRevoked())->toBeTrue()
        ->and($this->integration->fresh()->revoked_reason)->toContain('Two-factor');

    expectUnauthorized($this->client, 'TOKEN_REVOKED');
});

describe('creating integrations', function () {
    it('needs 2FA on the account', function () {
        $user = datasetUser(T::LIBRARIAN);

        app(IntegrationService::class)->create($user, 'x', 'cursor', 'full', 90, '123456');
    })->throws(App\Exceptions\Domain\InvalidStateException::class);

    it('needs a valid step-up code every time', function () {
        $user = datasetUser(T::LIBRARIAN);
        enableTwoFactorFor($user);

        expect(fn () => app(IntegrationService::class)->create($user, 'x', 'cursor', 'full', 90, '000000'))->toThrow(App\Exceptions\Domain\ValidationException::class);
        expect(McpIntegration::query()->where('user_id', $user->id)->count())->toBe(0);
    });

    it('rejects a replayed step-up code', function () {
        $user = datasetUser(T::LIBRARIAN);
        enableTwoFactorFor($user);
        $code = totpCode('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');

        app(IntegrationService::class)->create($user, 'first', 'cursor', 'full', 90, $code);

        expect(fn () => app(IntegrationService::class)->create($user, 'second', 'cursor', 'full', 90, $code))->toThrow(App\Exceptions\Domain\ValidationException::class);
    });

    it('enforces the per-user limit of active integrations', function () {
        $user = datasetUser(T::LIBRARIAN);

        foreach (range(1, 5) as $n) {
            mcpIntegrationFor($user);
        }

        expect(fn () => mcpIntegrationFor($user))->toThrow(App\Exceptions\Domain\ConflictException::class);

        app(IntegrationService::class)->revoke($user, McpIntegration::query()->where('user_id', $user->id)->first());
        mcpIntegrationFor($user);

        expect(McpIntegration::query()->where('user_id', $user->id)->active()->count())->toBe(5);
    });

    it('offers only 30, 90, 180 and 365 days, and "never" only when super admin allows it', function () {
        $user = datasetUser(T::LIBRARIAN);

        expect(fn () => mcpIntegrationFor($user, 'full', 45))->toThrow(App\Exceptions\Domain\ValidationException::class)
            ->and(fn () => mcpIntegrationFor($user, 'full', null))->toThrow(App\Exceptions\Domain\ValidationException::class);

        app(McpSettingsService::class)->configure(datasetUser(T::SUPER_ADMIN), allowNeverExpire: true);

        expect(mcpIntegrationFor($user, 'full', null)['integration']->expires_at)->toBeNull();

        foreach ([30, 90, 180, 365] as $days) {
            expect(mcpIntegrationFor($user, 'full', $days)['integration']->expires_at->diffInDays(now(), true))->toBeGreaterThan($days - 1);
        }
    });

    it('is refused while MCP is switched off for the user', function () {
        McpSetting::current()->update(['global_enabled' => false]);

        expect(fn () => mcpIntegrationFor(datasetUser(T::LIBRARIAN)))->toThrow(App\Exceptions\Domain\ForbiddenException::class);
    });
});

describe('read-only integrations', function () {
    it('list and call only read-only tools', function () {
        $admin = datasetUser(T::SUPER_ADMIN);
        ['token' => $token] = mcpIntegrationFor($admin, 'read_only');
        $client = new McpClient($this, $token);

        $tools = collect($client->tools());

        expect($tools->count())->toBeGreaterThan(20)
            ->and($tools->every(fn (array $tool): bool => $tool['annotations']['readOnlyHint'] === true))->toBeTrue()
            ->and($tools->pluck('name'))->not->toContain('course_create', 'user_deactivate', 'clearance_approve');

        $denied = $client->call('department_create', ['name' => 'X', 'code' => 'X']);
        expect($denied['payload']['error']['code'])->toBe('FORBIDDEN')
            ->and($denied['payload']['error']['context']['reason'])->toBe('READ_ONLY_INTEGRATION');

        expect($client->call('department_list')['isError'])->toBeFalse();
    });
});

it('rate limits a token per minute with RATE_LIMITED', function () {
    config(['mcp_access.rate_limit_per_minute' => 3]);

    foreach (range(1, 3) as $n) {
        $this->client->request('tools/list')->assertOk();
    }

    $this->client->request('tools/list')->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMITED');
});

it('lets a super admin stop someone else\'s integration with a reason and notifies the owner', function () {
    $admin = datasetUser(T::SUPER_ADMIN);

    expect(fn () => app(IntegrationService::class)->revoke($admin, $this->integration, ''))->toThrow(App\Exceptions\Domain\ValidationException::class);

    app(IntegrationService::class)->revoke($admin, $this->integration, 'Suspicious activity');

    expect($this->integration->fresh()->revoked_by)->toBe($admin->id)
        ->and($this->user->notifications()->get()->pluck('data.body')->implode(' '))->toContain('Suspicious activity');

    expectUnauthorized($this->client, 'TOKEN_REVOKED');
});

it('does not let a user revoke another user\'s integration', function () {
    app(IntegrationService::class)->revoke(datasetUser(T::LIBRARIAN), $this->integration);
})->throws(App\Exceptions\Domain\ForbiddenException::class);
