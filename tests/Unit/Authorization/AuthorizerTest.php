<?php

use App\Enums\RoleKey;
use App\Exceptions\Domain\ForbiddenException;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use App\Support\Authorization\ResourceScope;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

function userWithRoles(string ...$roles): User
{
    $user = User::factory()->create();

    foreach ($roles as $role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    return $user;
}

it('lets a super admin do anything, including unknown permissions', function () {
    $admin = userWithRoles(RoleKey::SuperAdmin->value);
    $course = Course::factory()->create();

    expect(authorizer()->allows($admin, 'course:update', $course))->toBeTrue()
        ->and(authorizer()->allows($admin, 'audit_log:view'))->toBeTrue()
        ->and(authorizer()->allows($admin, 'anything:at_all'))->toBeTrue()
        ->and(authorizer()->scopeFor($admin, 'student:list')->global)->toBeTrue();
});

it('denies a user without roles', function () {
    $user = User::factory()->create();

    expect(authorizer()->allows($user, 'course:list'))->toBeFalse()
        ->and(authorizer()->scopeFor($user, 'course:list')->granted)->toBeFalse();
});

it('throws a FORBIDDEN domain exception that names the roles holding the permission', function () {
    $student = userWithRoles(RoleKey::Student->value);

    try {
        authorizer()->authorize($student, 'result:publish');
        $this->fail('Expected ForbiddenException');
    } catch (ForbiddenException $exception) {
        expect($exception->errorCode())->toBe('FORBIDDEN')
            ->and($exception->httpStatus())->toBe(403)
            ->and($exception->context['permission'])->toBe('result:publish')
            ->and($exception->context['allowed_roles'])->toContain('super_admin');
    }
});

it('grants the union of permissions across all roles of a user', function () {
    $user = userWithRoles(RoleKey::Teacher->value, RoleKey::Librarian->value);

    expect(authorizer()->allows($user, 'result:enter_marks'))->toBeTrue()
        ->and(authorizer()->allows($user, 'library_loans:manage'))->toBeTrue()
        ->and(authorizer()->allows($user, 'result:publish'))->toBeFalse();
});

it('resolves spec permission names to existing Shield permissions through aliases', function () {
    $role = Role::findOrCreate('Academic Admin', 'web');
    $role->givePermissionTo(Permission::findOrCreate('Create:Course', 'web'));
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(authorizer()->allows($user, 'course:create'))->toBeTrue()
        ->and(authorizer()->allows($user, 'course:create', Department::factory()->create()))->toBeTrue()
        ->and(authorizer()->allows($user, 'course:delete'))->toBeFalse();
});

it('treats legacy roles as global', function () {
    $role = Role::findOrCreate('Academic Admin', 'web');
    $role->givePermissionTo(Permission::findOrCreate('Update:Course', 'web'));
    $user = User::factory()->create();
    $user->assignRole($role);

    expect(authorizer()->allows($user, 'course:update', Course::factory()->create()))->toBeTrue();
});

it('treats a directly assigned user permission as a global grant', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('audit_log:view');

    expect(authorizer()->allows($user, 'audit_log:view'))->toBeTrue()
        ->and(authorizer()->scopeFor($user, 'audit_log:view')->global)->toBeTrue();
});

it('denies everything to inactive or deleted users', function (string $state) {
    $user = userWithRoles(RoleKey::SuperAdmin->value);

    match ($state) {
        'inactive' => $user->update(['is_active' => false]),
        'deleted' => $user->delete(),
    };

    expect(authorizer()->allows($user, 'course:list'))->toBeFalse()
        ->and(authorizer()->scopeFor($user, 'course:list')->granted)->toBeFalse()
        ->and(fn () => authorizer()->authorize($user, 'course:list'))->toThrow(ForbiddenException::class);
})->with(['inactive', 'deleted']);

it('ignores scope for unscoped catalog permissions', function () {
    $teacher = userWithRoles(RoleKey::Teacher->value);
    $course = Course::factory()->create();

    expect(authorizer()->allows($teacher, 'course:view', $course))->toBeTrue()
        ->and(authorizer()->allows($teacher, 'result:enter_marks', $course))->toBeFalse();
});

it('fails closed for scoped roles on records without scope information', function () {
    $head = userWithRoles(RoleKey::DepartmentHead->value);

    expect(authorizer()->allows($head, 'course:update', ResourceScope::none()))->toBeFalse()
        ->and(authorizer()->allows($head, 'course:update'))->toBeTrue();
});

it('picks up permission matrix changes immediately', function () {
    $librarian = userWithRoles(RoleKey::Librarian->value);
    $role = Role::findByName(RoleKey::Librarian->value, 'web');

    expect(authorizer()->allows($librarian, 'library_dues:view'))->toBeTrue();

    $role->revokePermissionTo('library_dues:view');

    expect(authorizer()->allows($librarian->fresh(), 'library_dues:view'))->toBeFalse();

    $role->givePermissionTo('result:publish');

    expect(authorizer()->allows($librarian->fresh(), 'result:publish'))->toBeTrue();
});

it('lists the roles that hold a permission', function () {
    expect(authorizer()->rolesHolding('clearance:approve'))
        ->toContain('super_admin', 'hall_provost', 'librarian', 'department_head', 'head_of_institution')
        ->not->toContain('student');
});
