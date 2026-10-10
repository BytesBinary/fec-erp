<?php

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

function roleKeyMigration(): object
{
    return require database_path('migrations/2026_10_09_204007_rename_placeholder_roles_to_keys.php');
}

it('renames the placeholder roles to keys, keeping ids and assignments, and rolls back', function () {
    $migration = roleKeyMigration();
    $migration->down();

    $legacy = Role::query()->create(['name' => 'Department Head', 'guard_name' => 'web']);
    $user = App\Models\User::factory()->create();
    $user->assignRole($legacy);

    $migration->up();

    expect(Role::query()->find($legacy->id)->name)->toBe('department_head')
        ->and($user->fresh()->hasRole('department_head'))->toBeTrue()
        ->and(Role::query()->whereIn('name', ['admin_office', 'head_of_institution'])->count())->toBe(2);

    $migration->down();

    expect(Role::query()->find($legacy->id)->name)->toBe('Department Head')
        ->and(DB::table('roles')->whereIn('name', ['admin_office', 'head_of_institution'])->count())->toBe(0);
});

it('leaves an existing role key untouched instead of colliding', function () {
    $migration = roleKeyMigration();

    Role::query()->firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
    Role::query()->create(['name' => 'Teacher', 'guard_name' => 'web']);

    $migration->up();

    expect(Role::query()->where('name', 'teacher')->count())->toBe(1)
        ->and(Role::query()->where('name', 'Teacher')->exists())->toBeTrue();
});
