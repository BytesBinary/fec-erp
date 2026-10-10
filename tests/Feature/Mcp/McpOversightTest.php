<?php

use App\Filament\Pages\Security\McpOversight;
use App\Models\McpRoleAccess;
use App\Models\McpSetting;
use App\Services\Mcp\IntegrationService;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\Mcp\McpClient;

beforeEach(function () {
    seedTestDataset();
    $this->admin = datasetUser(T::SUPER_ADMIN);
    $this->teacher = datasetUser(T::TEACHER);
    ['integration' => $this->teacherIntegration, 'token' => $this->teacherToken] = mcpIntegrationFor($this->teacher, 'full', 90, 'cursor');
    ['integration' => $this->librarianIntegration] = mcpIntegrationFor(datasetUser(T::LIBRARIAN), 'read_only', 30, 'vscode');
    $this->actingAs($this->admin);
});

it('is only for users who may manage every integration', function () {
    expect(McpOversight::canAccess())->toBeTrue();

    $this->flushSession()->actingAs($this->teacher);
    expect(McpOversight::canAccess())->toBeFalse();
});

it('lists everyone\'s integrations and filters by user, role, client and status', function () {
    Livewire::test(McpOversight::class)
        ->assertSee($this->teacher->email)->assertSee(datasetUser(T::LIBRARIAN)->email)
        ->set('userFilter', 'tanvir')->assertSee($this->teacher->email)->assertDontSee(datasetUser(T::LIBRARIAN)->email)
        ->set('userFilter', '')->set('roleFilter', 'librarian')->assertSee(datasetUser(T::LIBRARIAN)->email)->assertDontSee($this->teacher->email)
        ->set('roleFilter', '')->set('clientFilter', 'cursor')->assertSee($this->teacher->email)->assertDontSee(datasetUser(T::LIBRARIAN)->email)
        ->set('clientFilter', '')->set('statusFilter', 'revoked')->assertDontSee($this->teacher->email);

    app(IntegrationService::class)->revoke($this->admin, $this->teacherIntegration, 'Test');

    Livewire::test(McpOversight::class)->set('statusFilter', 'revoked')->assertSee($this->teacher->email)->assertDontSee(datasetUser(T::LIBRARIAN)->email);
});

it('revokes with a reason, rejects the client immediately and notifies the user', function () {
    Livewire::test(McpOversight::class)
        ->callAction('revoke', ['reason' => 'Left the institution'], ['integration' => $this->teacherIntegration->id])
        ->assertHasNoActionErrors();

    $integration = $this->teacherIntegration->fresh();

    expect($integration->isRevoked())->toBeTrue()
        ->and($integration->revoked_by)->toBe($this->admin->id)
        ->and($integration->revoked_reason)->toBe('Left the institution');

    (new McpClient($this, $this->teacherToken))->request('tools/list')->assertUnauthorized()->assertJsonPath('error.code', 'TOKEN_REVOKED');

    $titles = $this->teacher->notifications()->get()->pluck('data.title')->all();
    expect($titles)->toContain('AI integration stopped')
        ->and($this->teacher->notifications()->get()->pluck('data.body')->implode(' '))->toContain('Left the institution');

    expect(App\Models\AuditLog::query()->where('action', 'mcp.integration_revoked')->latest('id')->first()->after['by_admin'])->toBeTrue();
});

it('requires a reason', function () {
    Livewire::test(McpOversight::class)->callAction('revoke', ['reason' => ''], ['integration' => $this->teacherIntegration->id])->assertHasActionErrors(['reason']);

    expect($this->teacherIntegration->fresh()->isRevoked())->toBeFalse();
});

it('switches MCP off and on globally; clients get MCP_DISABLED while it is off', function () {
    Livewire::test(McpOversight::class)->assertSee('ON')->call('toggleGlobal');

    expect(McpSetting::current()->global_enabled)->toBeFalse();
    (new McpClient($this, $this->teacherToken))->request('tools/list')->assertUnauthorized()->assertJsonPath('error.code', 'MCP_DISABLED');

    Livewire::test(McpOversight::class)->call('toggleGlobal');
    (new McpClient($this, $this->teacherToken))->request('tools/list')->assertOk();
});

it('switches MCP per role', function () {
    $role = Role::findByName('teacher', 'web');

    Livewire::test(McpOversight::class)->call('toggleRole', $role->id);

    expect(McpRoleAccess::query()->find($role->id)->enabled)->toBeFalse();
    (new McpClient($this, $this->teacherToken))->request('tools/list')->assertUnauthorized()->assertJsonPath('error.code', 'MCP_DISABLED');
    (new McpClient($this, mcpIntegrationFor(datasetUser(T::DEPT_HEAD_CSE))['token']))->request('tools/list')->assertOk();
});

it('allows never-expiring integrations only after the admin switch', function () {
    Livewire::test(McpOversight::class)->call('toggleNeverExpire');

    expect(McpSetting::current()->allow_never_expire)->toBeTrue();
});

it('shows a usage overview of calls and denied calls per day', function () {
    $client = new McpClient($this, $this->teacherToken);
    $client->call('me_get_profile');
    $client->call('user_create', []);
    $this->flushSession()->actingAs($this->admin);

    $usage = app(IntegrationService::class)->usageOverview($this->admin);

    expect($usage['active'])->toBe(3)
        ->and(array_sum($usage['calls_per_day']))->toBe(2)
        ->and(array_sum($usage['denied_per_day']))->toBe(1)
        ->and(array_keys($usage['calls_per_day']))->toHaveCount(7);

    Livewire::test(McpOversight::class)->assertSeeHtml('data-testid="active-count">3<');
});

it('refuses overview data to anyone without manage-all', function () {
    app(IntegrationService::class)->adminQuery($this->teacher);
})->throws(App\Exceptions\Domain\ForbiddenException::class);

describe('expiry notices', function () {
    it('notifies once about integrations expiring within 7 days', function () {
        $this->travel(24)->days();

        $this->artisan('mcp:notify-expiring')->assertSuccessful();

        $titles = datasetUser(T::LIBRARIAN)->notifications()->get()->pluck('data.title')->all();
        expect($titles)->toContain('AI integration expiring soon')
            ->and($this->teacher->notifications()->get()->pluck('data.title')->all())->not->toContain('AI integration expiring soon');

        $this->artisan('mcp:notify-expiring')->expectsOutputToContain('Notified about 0')->assertSuccessful();
    });

    it('is scheduled daily', function () {
        $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($event) => $event->command)->implode(' ');

        expect($events)->toContain('mcp:notify-expiring');
    });

    it('reports expiring soon in the status', function () {
        $this->travel(24)->days();

        expect($this->librarianIntegration->fresh()->status())->toBe('expiring_soon')
            ->and($this->teacherIntegration->fresh()->status())->toBe('active');
    });
});
