<?php

use App\Enums\Channel;
use App\Filament\Resources\Departments\Pages\CreateDepartment;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use App\Services\Academic\DepartmentService;
use App\Services\Audit\AuditLogger;
use App\Support\RequestContext;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();

    $this->admin = datasetUser(T::SUPER_ADMIN);
    $this->actingAs($this->admin);
});

it('records a web write made through a Filament page with actor, channel, ip and user agent', function () {
    Livewire::test(CreateDepartment::class)
        ->fillForm(['name' => 'Mechanical Engineering', 'code' => 'ME', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $department = Department::query()->where('code', 'ME')->firstOrFail();
    $log = AuditLog::query()->where('action', 'department.created')->where('entity_id', $department->id)->firstOrFail();

    expect($log->actor_user_id)->toBe($this->admin->id)
        ->and($log->channel)->toBe(Channel::Web)
        ->and($log->entity_type)->toBe('Department')
        ->and($log->before)->toBeNull()
        ->and($log->after)->toMatchArray(['name' => 'Mechanical Engineering', 'code' => 'ME'])
        ->and($log->ip)->toBe('127.0.0.1')
        ->and($log->user_agent)->not->toBeEmpty()
        ->and($log->created_at)->not->toBeNull();
});

it('records before and after values of an update', function () {
    $department = Department::query()->where('code', T::DEPT_CSE)->firstOrFail();

    app(DepartmentService::class)->update($this->admin, $department, ['name' => 'Computer Science']);

    $log = AuditLog::query()->where('action', 'department.updated')->latest('id')->firstOrFail();

    expect($log->before)->toBe(['name' => 'Computer Science and Engineering'])
        ->and($log->after)->toBe(['name' => 'Computer Science']);
});

it('tags writes with the mcp channel and integration id', function () {
    app(RequestContext::class)->runAs(Channel::Mcp, function (): void {
        app(DepartmentService::class)->create($this->admin, ['name' => 'Civil Engineering', 'code' => 'CE']);
    }, integrationId: 42);

    $log = AuditLog::query()->where('action', 'department.created')->latest('id')->firstOrFail();

    expect($log->channel)->toBe(Channel::Mcp)
        ->and($log->integration_id)->toBe(42);

    expect(app(RequestContext::class)->channel())->toBe(Channel::Web)
        ->and(app(RequestContext::class)->integrationId())->toBeNull();
});

it('tags writes with the assistant channel', function () {
    app(RequestContext::class)->runAs(Channel::Assistant, fn () => app(DepartmentService::class)
        ->delete($this->admin, Department::query()->where('code', T::DEPT_EEE)->firstOrFail()));

    $log = AuditLog::query()->where('action', 'department.deleted')->latest('id')->firstOrFail();

    expect($log->channel)->toBe(Channel::Assistant)
        ->and($log->before)->toMatchArray(['code' => T::DEPT_EEE])
        ->and($log->after)->toBeNull();
});

it('never stores secrets and records a password change as a flag', function () {
    $user = User::factory()->create();
    $user->update(['password' => 'a-new-password-123']);

    $created = AuditLog::query()->where('action', 'user.created')->where('entity_id', $user->id)->firstOrFail();
    $updated = AuditLog::query()->where('action', 'user.updated')->where('entity_id', $user->id)->firstOrFail();

    expect($created->after)->not->toHaveKeys(['password', 'remember_token'])
        ->and($updated->after)->toBe(['password_changed' => true])
        ->and(json_encode($updated->before))->not->toContain('$2y$');
});

it('ignores remember-token-only updates', function () {
    $count = AuditLog::query()->count();

    $this->admin->update(['remember_token' => 'abc123']);

    expect(AuditLog::query()->count())->toBe($count);
});

it('does not record actor-less console writes unless configured', function () {
    auth()->logout();

    Department::factory()->create();
    expect(AuditLog::query()->where('action', 'department.created')->count())->toBe(0);

    config(['erp.audit.record_system_writes_without_actor' => true]);
    Department::factory()->create();
    expect(AuditLog::query()->where('action', 'department.created')->count())->toBe(1);
});

it('can pause auditing for bulk operations', function () {
    app(AuditLogger::class)->withoutAuditing(fn () => Department::factory()->count(3)->create());

    expect(AuditLog::query()->where('action', 'department.created')->count())->toBe(0);
});

it('shows the audit log to super admins only', function () {
    app(DepartmentService::class)->create($this->admin, ['name' => 'Architecture', 'code' => 'ARCH']);

    $this->get('/audit-logs')->assertSuccessful()->assertSee('department.created');

    $log = AuditLog::query()->latest('id')->firstOrFail();
    $this->get("/audit-logs/{$log->id}")->assertSuccessful()->assertSee('Architecture');
});

it('forbids the audit log to other roles', function (string $email) {
    $this->actingAs(datasetUser($email))->get('/audit-logs')->assertForbidden();
})->with([T::DEPT_HEAD_CSE, T::ADMIN_OFFICE, T::STUDENT_ELIGIBLE]);

it('attributes service writes to the acting user even without a web session', function () {
    auth()->logout();

    app(RequestContext::class)->runAs(Channel::Mcp, fn () => app(DepartmentService::class)
        ->create(datasetUser(T::SUPER_ADMIN), ['name' => 'Textile Engineering', 'code' => 'TE']));

    $log = AuditLog::query()->where('action', 'department.created')->latest('id')->firstOrFail();

    expect($log->actor_user_id)->toBe(datasetUser(T::SUPER_ADMIN)->id)
        ->and($log->channel)->toBe(Channel::Mcp);
});
