<?php

use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Students\Pages\ListStudents;
use App\Models\Course;
use App\Models\Student;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();
});

it('lets a department head edit courses of their own department only', function () {
    $own = Course::query()->where('code', 'CSE-1201')->firstOrFail();
    $other = Course::query()->where('code', 'EEE-1201')->firstOrFail();

    $this->actingAs(datasetUser(T::DEPT_HEAD_CSE));

    $this->get("/courses/{$own->id}/edit")->assertSuccessful();
    $this->get("/courses/{$other->id}/edit")->assertForbidden();
});

it('saves a course edit through the service and rejects moving it out of scope', function () {
    $course = Course::query()->where('code', 'CSE-1201')->firstOrFail();
    $eee = App\Models\Department::query()->where('code', T::DEPT_EEE)->firstOrFail();

    $this->actingAs(datasetUser(T::DEPT_HEAD_CSE));

    Livewire::test(EditCourse::class, ['record' => $course->getRouteKey()])
        ->fillForm(['name' => 'Data Structures and Algorithms'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($course->fresh()->name)->toBe('Data Structures and Algorithms');

    Livewire::test(EditCourse::class, ['record' => $course->getRouteKey()])
        ->fillForm(['department_id' => $eee->id])
        ->call('save')
        ->assertNotified();

    expect($course->fresh()->department_id)->not->toBe($eee->id);
});

it('shows a department head only students of their department', function () {
    $this->actingAs(datasetUser(T::DEPT_HEAD_CSE));

    $cse = Student::query()->where('roll_number', 'like', 'CSE-%')->get();
    $eee = Student::query()->where('roll_number', 'like', 'EEE-%')->get();

    Livewire::test(ListStudents::class)
        ->assertCanSeeTableRecords($cse)
        ->assertCanNotSeeTableRecords($eee);
});

it('shows the administration office every student', function () {
    $this->actingAs(datasetUser(T::ADMIN_OFFICE));

    Livewire::test(ListStudents::class)->assertCanSeeTableRecords(Student::query()->get());
});

it('blocks roles without the permission from a resource', function () {
    $this->actingAs(datasetUser(T::LIBRARIAN));

    $this->get('/courses')->assertForbidden();
});

it('blocks inactive users from the panel', function () {
    $user = datasetUser(T::ADMIN_OFFICE);
    $user->update(['is_active' => false]);

    expect($user->fresh()->canAccessPanel(Filament\Facades\Filament::getPanel('erp')))->toBeFalse();
});
