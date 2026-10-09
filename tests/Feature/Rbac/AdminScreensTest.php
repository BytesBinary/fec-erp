<?php

use App\Filament\Resources\Notices\Pages\ListNotices;
use App\Filament\Resources\Semesters\Pages\ListSemesters;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Department;
use App\Models\Notice;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();
});

it('renders the new admin screens for a super admin', function (string $path) {
    $this->actingAs(datasetUser(T::SUPER_ADMIN))->get($path)->assertSuccessful();
})->with(['/users', '/users/create', '/programs', '/programs/create', '/semesters', '/halls', '/notices', '/notices/create', '/audit-logs']);

it('lets readers browse catalog screens but not manage users', function () {
    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE));

    $this->get('/programs')->assertSuccessful();
    $this->get('/notices')->assertSuccessful();
    $this->get('/programs/create')->assertForbidden();
    $this->get('/users')->assertForbidden();
});

it('assigns a scoped role from the user edit page', function () {
    $this->actingAs(datasetUser(T::SUPER_ADMIN));
    $user = User::factory()->create();
    $eee = Department::query()->where('code', T::DEPT_EEE)->firstOrFail();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('assignRole', data: ['role' => 'department_head', 'scope_ids' => [$eee->id]])
        ->assertNotified('Role assigned.');

    expect($user->fresh()->hasRole('department_head'))->toBeTrue()
        ->and($user->fresh()->roleScopes->pluck('scope_id')->all())->toBe([$eee->id]);

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('revokeRole', data: ['role' => 'department_head'])
        ->assertNotified('Role revoked.');

    expect($user->fresh()->hasRole('department_head'))->toBeFalse();
});

it('reports a domain error from the role action as a notification', function () {
    $this->actingAs(datasetUser(T::SUPER_ADMIN));
    $user = User::factory()->create();

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('assignRole', data: ['role' => 'super_admin'])
        ->assertNotified('Role assigned.');

    Livewire::test(EditUser::class, ['record' => datasetUser(T::SUPER_ADMIN)->getRouteKey()])
        ->callAction('revokeRole', data: ['role' => 'super_admin'])
        ->assertNotified('You cannot remove your own super admin role.');
});

it('sets the active semester from the semester list', function () {
    $this->actingAs(datasetUser(T::SUPER_ADMIN));
    $semester = Semester::query()->where('code', 'SP2025')->firstOrFail();

    Livewire::test(ListSemesters::class)->callTableAction('setActive', $semester);

    expect(Semester::active()?->code)->toBe('SP2025');
});

it('hides draft notices from readers', function () {
    $published = Notice::factory()->create(['title' => 'Exam schedule']);
    $draft = Notice::factory()->draft()->create(['title' => 'Unfinished']);

    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE));
    Livewire::test(ListNotices::class)->assertCanSeeTableRecords([$published])->assertCanNotSeeTableRecords([$draft]);
});
