<?php

use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\NotFoundException;
use App\Exceptions\Domain\ValidationException;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\RoleScope;
use App\Models\User;
use App\Services\Users\RoleService;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();

    $this->roles = app(RoleService::class);
    $this->admin = datasetUser(T::SUPER_ADMIN);
    $this->actingAs($this->admin);
});

it('assigns a scoped department head and the new scope takes effect', function () {
    $user = User::factory()->create();
    $eee = Department::query()->where('code', T::DEPT_EEE)->firstOrFail();

    $this->roles->assign($this->admin, $user, 'department_head', [$eee->id]);

    expect($user->fresh()->hasRole('department_head'))->toBeTrue()
        ->and($this->roles->rolesOf($this->admin, $user->fresh()))->toBe([
            ['role' => 'department_head', 'scope_type' => 'department', 'scope_ids' => [$eee->id]],
        ])
        ->and(authorizer()->allows($user->fresh(), 'course:update', $eee->courses()->first()))->toBeTrue();

    expect(AuditLog::query()->where('action', 'user.role_assigned')->where('entity_id', $user->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'user.role_scopes_changed')->where('entity_id', $user->id)->first()->after)
        ->toMatchArray(['role' => 'department_head', 'scope_type' => 'department', 'scope_ids' => [$eee->id]]);
});

it('requires a scope for department heads and provosts', function (string $role) {
    $this->roles->assign($this->admin, User::factory()->create(), $role, []);
})->with(['department_head', 'hall_provost'])->throws(ValidationException::class);

it('rejects unknown scope ids and scopes on global roles', function () {
    expect(fn () => $this->roles->assign($this->admin, User::factory()->create(), 'hall_provost', [9999]))
        ->toThrow(ValidationException::class)
        ->and(fn () => $this->roles->assign($this->admin, User::factory()->create(), 'librarian', [1]))
        ->toThrow(ValidationException::class);
});

it('rejects unknown roles', function () {
    $this->roles->assign($this->admin, User::factory()->create(), 'janitor');
})->throws(NotFoundException::class);

it('does not let non-admins assign roles', function () {
    $this->roles->assign(datasetUser(T::DEPT_HEAD_CSE), User::factory()->create(), 'teacher');
})->throws(ForbiddenException::class);

it('only lets a super admin grant super admin', function () {
    $delegate = User::factory()->create();
    $delegate->givePermissionTo('role:assign');

    expect(fn () => $this->roles->assign($delegate, User::factory()->create(), 'super_admin'))->toThrow(ForbiddenException::class);

    $target = User::factory()->create();
    $this->roles->assign($delegate, $target, 'librarian');

    expect($target->fresh()->hasRole('librarian'))->toBeTrue();
});

it('revokes a role together with its scopes', function () {
    $provost = datasetUser(T::PROVOST_A);

    $this->roles->revoke($this->admin, $provost, 'hall_provost');

    expect($provost->fresh()->hasRole('hall_provost'))->toBeFalse()
        ->and(RoleScope::query()->where('user_id', $provost->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'user.role_revoked')->where('entity_id', $provost->id)->exists())->toBeTrue();
});

it('stops super admins from removing their own super admin role', function () {
    $this->roles->revoke($this->admin, $this->admin, 'super_admin');
})->throws(ForbiddenException::class);
