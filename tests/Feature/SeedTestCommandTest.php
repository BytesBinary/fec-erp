<?php

use App\Enums\RoleKey;
use App\Models\Hall;
use App\Models\RoleScope;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Hash;

it('seeds one account per role with the shared test password', function () {
    $this->artisan('seed:test')->assertSuccessful();

    foreach (T::accountsByRole() as $role => $email) {
        $user = User::query()->where('email', $email)->firstOrFail();

        expect($user->hasRole($role))->toBeTrue()
            ->and(Hash::check(T::PASSWORD, $user->password))->toBeTrue();
    }

    expect(collect(RoleKey::values())->diff(array_keys(T::accountsByRole())))->toBeEmpty();
});

it('seeds two scoped department heads and two scoped provosts', function () {
    $this->artisan('seed:test')->assertSuccessful();

    expect(User::role('department_head')->count())->toBe(2)
        ->and(User::role('hall_provost')->count())->toBe(2)
        ->and(RoleScope::query()->where('scope_type', 'department')->pluck('scope_id')->unique())->toHaveCount(2)
        ->and(RoleScope::query()->where('scope_type', 'hall')->pluck('scope_id')->sort()->values()->all())
        ->toBe(Hall::query()->orderBy('id')->pluck('id')->all());

    expect(datasetUser(T::DEPT_HEAD_CSE)->hasRole('teacher'))->toBeTrue();
});

it('seeds the five named students', function () {
    $this->artisan('seed:test')->assertSuccessful();

    expect(Student::query()->count())->toBe(5);

    foreach ([T::STUDENT_INCOMPLETE, T::STUDENT_UNFINISHED, T::STUDENT_ELIGIBLE, T::STUDENT_NON_RESIDENT, T::STUDENT_LIBRARY_LOAN] as $email) {
        expect(datasetUser($email)->student)->not->toBeNull();
    }
});

it('is idempotent', function () {
    $this->artisan('seed:test')->assertSuccessful();
    $counts = [User::query()->count(), Student::query()->count(), RoleScope::query()->count()];

    $this->artisan('seed:test')->assertSuccessful();

    expect([User::query()->count(), Student::query()->count(), RoleScope::query()->count()])->toBe($counts);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('seed:test')->assertFailed();

    expect(User::query()->count())->toBe(0);
});
