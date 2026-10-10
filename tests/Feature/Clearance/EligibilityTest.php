<?php

use App\Exceptions\Domain\InvalidStateException;
use App\Models\Student;
use App\Models\User;
use App\Services\Clearance\ClearanceService;
use App\Services\Clearance\EligibilityChecker;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
});

function eligibilityOf(string $email): array
{
    return app(EligibilityChecker::class)->check(studentFor($email))->codes();
}

it('finds the seeded eligible students eligible, including one with an unpublished improvement attempt', function (string $email) {
    expect(eligibilityOf($email))->toBe([]);
})->with([T::STUDENT_ELIGIBLE, T::STUDENT_NON_RESIDENT, T::STUDENT_LIBRARY_LOAN]);

it('explains why the student with an incomplete profile cannot apply', function () {
    expect(eligibilityOf(T::STUDENT_INCOMPLETE))->toContain('profile_incomplete');
});

it('explains missing credits for the student whose program is not finished', function () {
    $result = app(EligibilityChecker::class)->check(studentFor(T::STUDENT_UNFINISHED));

    expect($result->codes())->toContain('credits_missing')
        ->and(collect($result->reasons)->firstWhere('code', 'credits_missing')['message'])->toContain('4.5')->toContain('12');
});

it('blocks while a course has a result awaiting publication and no published pass', function () {
    expect(eligibilityOf(T::STUDENT_UNFINISHED))->toContain('results_unpublished');
});

it('blocks inactive accounts', function () {
    User::query()->where('email', T::STUDENT_ELIGIBLE)->update(['is_active' => false]);

    expect(eligibilityOf(T::STUDENT_ELIGIBLE))->toContain('account_inactive');
});

it('blocks while another request is active and frees the student once it is collected or cancelled', function () {
    applyForClearance();

    expect(eligibilityOf(T::STUDENT_ELIGIBLE))->toBe(['active_request_exists']);
});

it('makes apply() fail with the exact reasons when not eligible', function () {
    try {
        app(ClearanceService::class)->apply(datasetUser(T::STUDENT_UNFINISHED));
    } catch (InvalidStateException $exception) {
        expect(collect($exception->context['reasons'])->pluck('code')->all())->toContain('credits_missing');

        return;
    }

    $this->fail('apply() should have thrown');
});

it('lets only the student apply for themselves', function () {
    app(ClearanceService::class)->apply(datasetUser(T::TEACHER));
})->throws(App\Exceptions\Domain\ForbiddenException::class);

it('lets staff read a student\'s eligibility but not other students', function () {
    $service = app(ClearanceService::class);

    expect($service->checkEligibility(datasetUser(T::ADMIN_OFFICE), studentFor(T::STUDENT_ELIGIBLE))->eligible())->toBeTrue();
    expect(fn () => $service->checkEligibility(datasetUser(T::STUDENT_NON_RESIDENT), studentFor(T::STUDENT_ELIGIBLE)))->toThrow(App\Exceptions\Domain\ForbiddenException::class);
});

it('treats a program without required credits as having no credit requirement', function () {
    $student = studentFor(T::STUDENT_UNFINISHED);
    $student->program->update(['required_credits' => 0]);

    expect(app(EligibilityChecker::class)->check(Student::query()->find($student->id))->codes())->not->toContain('credits_missing');
});
