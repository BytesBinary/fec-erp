<?php

use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    seedTestDataset();
});

it('lets the super admin open the role editor and see the seeded roles', function () {
    $this->actingAs(datasetUser(T::SUPER_ADMIN))->get('/shield/roles')->assertOk()->assertSee('Roles');

    Livewire::actingAs(datasetUser(T::SUPER_ADMIN))
        ->test(ListRoles::class)
        ->assertCanSeeTableRecords(Role::query()->whereIn('name', ['super_admin', 'hall_provost', 'student'])->get());
});

it('lets the super admin create a role and change its permissions', function () {
    $admin = datasetUser(T::SUPER_ADMIN);

    Livewire::actingAs($admin)
        ->test(CreateRole::class)
        ->fillForm(['name' => 'exam_observer'])
        ->call('create')
        ->assertHasNoFormErrors();

    $role = Role::query()->where('name', 'exam_observer')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(EditRole::class, ['record' => $role->getKey()])
        ->assertSuccessful();

    $role->givePermissionTo('ViewAny:Student');

    expect($role->fresh()->hasPermissionTo('ViewAny:Student'))->toBeTrue();
});

it('keeps the role editor away from non-admins', function () {
    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get('/shield/roles')->assertForbidden();
    $this->flushSession();
    $this->actingAs(datasetUser(T::TEACHER))->get('/shield/roles')->assertForbidden();
});
