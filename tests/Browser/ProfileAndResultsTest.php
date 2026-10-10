<?php

use App\Models\Student;
use App\Models\StudentProfile;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedTestDataset();
});

it('E2E 1: an incomplete student is sent to the profile, cannot reach Result, completes it and reaches the dashboard', function () {
    $page = uiLogin(T::STUDENT_INCOMPLETE);

    $page->assertPathIs('/profile/complete')->assertSee('Complete your profile')->assertSee('0 of');

    $page->navigate('/results')->assertPathIs('/profile/complete');
    $page->navigate('/')->assertPathIs('/profile/complete');

    $page->fill('[id="form.full_name_certificate"]', 'Arif Chowdhury')
        ->fill('[id="form.father_name"]', 'Rahim Chowdhury')
        ->fill('[id="form.mother_name"]', 'Karima Chowdhury')
        ->fill('[id="form.date_of_birth"]', '2002-05-20')
        ->fill('[id="form.nid_or_birth_reg"]', '2002123456789')
        ->select('[id="form.blood_group"]', 'O+')
        ->fill('[id="form.phone"]', '01712345678')
        ->fill('[id="form.email"]', 'arif@example.com')
        ->fill('[id="form.present_address"]', '12 Park Road, Dhaka')
        ->fill('[id="form.permanent_address"]', 'Village Kuti, Cumilla')
        ->fill('[id="form.guardian_phone"]', '01812345678')
        ->fill('[id="form.emergency_contact_phone"]', '01912345678');

    $page->click('Save profile')->wait(2)->assertSee('Saved. Some required fields are still missing.')->assertPathIs('/profile/complete')->assertSee('Photo is required');

    // The browser test server cannot receive multipart uploads, so the photo upload itself is
    // simulated by storing the file and the profile column directly (docs/DECISIONS.md D-017).
    Storage::disk('public')->put('student-photos/arif.png', (string) file_get_contents(base_path('tests/fixtures/photo.png')));
    StudentProfile::query()->where('student_id', Student::query()->where('user_id', datasetUser(T::STUDENT_INCOMPLETE)->id)->value('id'))->update(['photo_path' => 'student-photos/arif.png']);
    $page->navigate('/profile/complete')->wait(2);
    $page->click('Save profile')->wait(3);

    $page->assertPathIs('/')->assertSee('Dashboard');
    $page->navigate('/results')->assertPathIs('/results')->assertSee('No published results yet');
});

it('E2E 2: the student sees every published semester GPA and the CGPA, but not the unpublished semester', function () {
    $expected = T::EXPECTED_RESULTS[T::STUDENT_ELIGIBLE];

    $page = uiLogin(T::STUDENT_ELIGIBLE);
    $page->assertPathIs('/')->click('Result')->wait(2)->assertPathIs('/results');

    $page->assertSee('CGPA')->assertSeeIn('[data-testid=cgpa]', $expected['cgpa']);
    $page->assertCount('[data-testid=semester-block]', 3);

    $gpas = $page->script("Array.from(document.querySelectorAll('[data-testid=semester-gpa]')).map(e => e.textContent.trim()).join(',')");
    expect($gpas)->toBe(implode(',', array_values($expected['semesters'])));

    $page->assertSee('Spring 2024')->assertSee('Fall 2024')->assertSee('Spring 2025')->assertDontSee('Fall 2025');
    $page->assertSeeIn('[data-testid=credits-earned]', '12');
});
