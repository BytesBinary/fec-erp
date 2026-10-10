<?php

use App\Exceptions\Domain\ForbiddenException;
use App\Filament\Pages\Profile\CompleteProfile;
use App\Models\ProfileRequiredField;
use App\Models\Student;
use App\Models\StudentProfile;
use App\Services\Profile\ProfileCompletionChecker;
use App\Services\Profile\ProfileService;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();
});

function studentOf(string $email): Student
{
    return Student::query()->where('user_id', datasetUser($email)->id)->firstOrFail();
}

it('redirects an incomplete student from every page to the profile page', function () {
    $this->actingAs(datasetUser(T::STUDENT_INCOMPLETE));

    $this->get('/')->assertRedirect(CompleteProfile::getUrl());
    $this->get('/results')->assertRedirect(CompleteProfile::getUrl());
    $this->get('/results/print')->assertRedirect(CompleteProfile::getUrl());
    $this->get(CompleteProfile::getUrl())->assertOk()->assertSee('Complete your profile');
});

it('answers json requests of an incomplete student with PROFILE_INCOMPLETE', function () {
    $this->actingAs(datasetUser(T::STUDENT_INCOMPLETE))->getJson('/results/print')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'PROFILE_INCOMPLETE');
});

it('lets students with a complete profile and all other roles through', function () {
    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get('/')->assertOk();
    $this->flushSession()->actingAs(datasetUser(T::TEACHER))->get('/')->assertOk();
    $this->flushSession()->actingAs(datasetUser(T::SUPER_ADMIN))->get('/')->assertOk();
});

it('gates a student again when super admin adds a new required field', function () {
    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get('/')->assertOk();

    StudentProfile::query()->where('student_id', studentOf(T::STUDENT_ELIGIBLE)->id)->update(['guardian_name' => null]);
    app(ProfileService::class)->configureField(datasetUser(T::SUPER_ADMIN), 'guardian_name', required: true);

    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE))->get('/')->assertRedirect(CompleteProfile::getUrl());
});

it('only lets super admin configure the required fields', function () {
    app(ProfileService::class)->configureField(datasetUser(T::TEACHER), 'guardian_name', true);
})->throws(ForbiddenException::class);

it('reports exactly which required fields are missing or invalid', function () {
    $student = studentOf(T::STUDENT_INCOMPLETE);
    $checker = app(ProfileCompletionChecker::class);

    expect($checker->isComplete($student))->toBeFalse()
        ->and($checker->progress($student)['percent'])->toBe(0)
        ->and(array_keys($checker->problems($student)))->toContain('photo', 'father_name', 'phone');

    expect($checker->isComplete(studentOf(T::STUDENT_ELIGIBLE)))->toBeTrue()
        ->and($checker->progress(studentOf(T::STUDENT_ELIGIBLE))['percent'])->toBe(100);
});

it('lets the student complete the profile through the page and then reaches the dashboard', function () {
    $user = datasetUser(T::STUDENT_INCOMPLETE);
    $this->actingAs($user);

    Livewire::test(CompleteProfile::class)
        ->fillForm([
            'full_name_certificate' => 'Arif Chowdhury',
            'father_name' => 'Rahim Chowdhury',
            'mother_name' => 'Karima Chowdhury',
            'date_of_birth' => '2002-05-20',
            'phone' => '01712345678',
            'email' => 'arif@example.com',
            'present_address' => '12 Park Road, Dhaka',
            'permanent_address' => 'Village Kuti, Cumilla',
            'guardian_phone' => '01812345678',
            'blood_group' => 'O+',
            'nid_or_birth_reg' => '2002123456789',
            'is_residential' => false,
            'emergency_contact_phone' => '01912345678',
            'photo_path' => ['student-photos/arif.png'],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = studentOf(T::STUDENT_INCOMPLETE)->profile;
    expect($profile->profile_completed_at)->not->toBeNull();

    $this->get('/')->assertOk();
});

it('rejects invalid values with per-field messages and keeps the profile incomplete', function () {
    $user = datasetUser(T::STUDENT_INCOMPLETE);

    expect(fn () => app(ProfileService::class)->save($user, studentOf(T::STUDENT_INCOMPLETE), ['phone' => '12', 'email' => 'nope', 'nid_or_birth_reg' => '123', 'blood_group' => 'Z+', 'date_of_birth' => '2999-01-01']))
        ->toThrow(App\Exceptions\Domain\ValidationException::class);

    expect(studentOf(T::STUDENT_INCOMPLETE)->profile?->profile_completed_at)->toBeNull();
});

it('requires a hall for residential students', function () {
    $user = datasetUser(T::STUDENT_INCOMPLETE);

    expect(fn () => app(ProfileService::class)->save($user, studentOf(T::STUDENT_INCOMPLETE), ['is_residential' => true]))
        ->toThrow(App\Exceptions\Domain\ValidationException::class);
});

it('clears profile_completed_at when a required field is emptied', function () {
    $user = datasetUser(T::STUDENT_ELIGIBLE);
    $student = studentOf(T::STUDENT_ELIGIBLE);

    app(ProfileService::class)->save($user, $student, ['mother_name' => null]);

    expect($student->profile->fresh()->profile_completed_at)->toBeNull()
        ->and(app(ProfileService::class)->isGated($user))->toBeTrue();
});

it('locks name, parents and date of birth after clearance is requested, but admin office may still edit them', function () {
    $user = datasetUser(T::STUDENT_ELIGIBLE);
    $student = studentOf(T::STUDENT_ELIGIBLE);
    $service = app(ProfileService::class);

    $service->lockFieldsForClearance($student);

    expect(fn () => $service->save($user, $student, ['father_name' => 'Someone Else']))->toThrow(ForbiddenException::class);
    expect(fn () => $service->save($user, $student, ['date_of_birth' => '2000-01-01']))->toThrow(ForbiddenException::class);

    $service->save($user, $student, ['present_address' => '99 New Road, Dhaka', 'father_name' => 'Abdul Karim']);
    expect($student->profile->fresh()->present_address)->toBe('99 New Road, Dhaka');

    $service->save(datasetUser(T::ADMIN_OFFICE), $student, ['father_name' => 'Corrected Name']);
    expect($student->profile->fresh()->father_name)->toBe('Corrected Name');
});

it('does not let a student read or edit another student\'s profile', function () {
    $service = app(ProfileService::class);
    $other = studentOf(T::STUDENT_NON_RESIDENT);

    expect(fn () => $service->describe(datasetUser(T::STUDENT_ELIGIBLE), $other))->toThrow(ForbiddenException::class)
        ->and(fn () => $service->save(datasetUser(T::STUDENT_ELIGIBLE), $other, ['email' => 'x@y.com']))->toThrow(ForbiddenException::class);
});

it('keeps the default required set as specified', function () {
    expect(ProfileRequiredField::query()->where('required', true)->pluck('field_key')->all())
        ->toContain('full_name_certificate', 'father_name', 'mother_name', 'date_of_birth', 'phone', 'email', 'present_address', 'permanent_address', 'photo', 'guardian_phone', 'blood_group', 'nid_or_birth_reg', 'hall', 'emergency_contact_phone');
});
