<?php

use App\Enums\ClearanceStatus;
use App\Models\ClearanceRequest;
use App\Models\McpIntegration;
use App\Models\Student;
use App\Models\User;
use App\Models\UserMfa;
use App\Services\Clearance\HashChain;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    $this->artisan('seed:demo')->assertSuccessful();
});

it('seeds a realistic, alive dataset', function () {
    expect(Student::query()->count())->toBeGreaterThanOrEqual(35)
        ->and(App\Models\Notice::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(App\Models\LibraryLoan::query()->count())->toBeGreaterThanOrEqual(5)
        ->and(App\Models\HallResidency::query()->current()->count())->toBeGreaterThanOrEqual(10)
        ->and(App\Models\Result::query()->published()->count())->toBeGreaterThan(100);
});

it('has an account for every role', function () {
    foreach (T::accountsByRole() as $role => $email) {
        expect(User::query()->where('email', $email)->first()?->hasRole($role))->toBeTrue($role);
    }
});

it('puts clearance requests into every state with intact hash chains', function () {
    $states = ClearanceRequest::query()->pluck('status')->map(fn (ClearanceStatus $status): string => $status->value)->unique()->sort()->values()->all();

    expect($states)->toContain('collected', 'ready_for_collection', 'rejected', 'pending');

    foreach (ClearanceRequest::query()->get() as $request) {
        expect(app(HashChain::class)->verify($request)['intact'])->toBeTrue();
    }
});

it('never contains the test-only 2FA user, TOTP secret or MCP integration', function () {
    expect(User::query()->where('email', T::MFA_USER)->exists())->toBeFalse()
        ->and(User::query()->where('email', T::MCP_USER)->exists())->toBeFalse()
        ->and(UserMfa::query()->count())->toBe(0)
        ->and(McpIntegration::query()->count())->toBe(0);
});

it('is idempotent', function () {
    $students = Student::query()->count();
    $requests = ClearanceRequest::query()->count();

    $this->artisan('seed:demo')->assertSuccessful();

    expect(Student::query()->count())->toBe($students)->and(ClearanceRequest::query()->count())->toBe($requests);
});

it('refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('seed:demo')->assertFailed();
});
