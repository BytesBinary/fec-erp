<?php

use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\ValidationException;
use App\Models\AuditLog;
use App\Services\Users\PermissionMatrixService;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();

    $this->matrix = app(PermissionMatrixService::class);
    $this->admin = datasetUser(T::SUPER_ADMIN);
    $this->actingAs($this->admin);
});

it('returns the matrix in spec permission names', function () {
    $matrix = $this->matrix->get($this->admin);

    expect($matrix['department_head'])->toContain('course:create', 'course:update', 'clearance:approve', 'result:approve')
        ->and($matrix['student'])->toContain('clearance:apply', 'result:view')
        ->and($matrix['student'])->not->toContain('clearance:approve');
});

it('is seeded as data, so changes take effect without code changes', function () {
    $student = datasetUser(T::STUDENT_ELIGIBLE);

    expect(authorizer()->allows($student, 'notice:create'))->toBeFalse();

    $after = $this->matrix->update($this->admin, 'student', grant: ['notice:create'], revoke: ['clearance:cancel']);

    expect($after)->toContain('notice:create')->not->toContain('clearance:cancel')
        ->and(authorizer()->allows($student->fresh(), 'notice:create'))->toBeTrue()
        ->and(authorizer()->allows($student->fresh(), 'clearance:cancel'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'role.permission_granted')->latest('id')->first()->after)
        ->toBe(['permissions' => ['notice:create']]);
});

it('stores aliased permissions as their Shield permission', function () {
    $this->matrix->update($this->admin, 'librarian', grant: ['course:create']);

    expect(\Spatie\Permission\Models\Role::findByName('librarian', 'web')->hasPermissionTo('Create:Course'))->toBeTrue();
});

it('rejects unknown permissions and edits of the super admin role', function () {
    expect(fn () => $this->matrix->update($this->admin, 'student', grant: ['made:up']))->toThrow(ValidationException::class)
        ->and(fn () => $this->matrix->update($this->admin, 'super_admin', revoke: ['course:create']))->toThrow(InvalidStateException::class);
});

it('is restricted to holders of permission_matrix permissions', function () {
    $this->matrix->get(datasetUser(T::ADMIN_OFFICE));
})->throws(ForbiddenException::class);

it('seeds idempotently without undoing admin changes', function () {
    $this->matrix->update($this->admin, 'student', revoke: ['clearance:cancel']);

    $this->artisan('seed:test')->assertSuccessful();

    $student = datasetUser(T::STUDENT_ELIGIBLE);

    expect(authorizer()->allows($student, 'clearance:apply'))->toBeTrue()
        ->and(authorizer()->allows($student, 'clearance:cancel'))->toBeFalse();
});
