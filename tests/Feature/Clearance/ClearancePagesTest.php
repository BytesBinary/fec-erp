<?php

use App\Filament\Pages\Clearance\Apply;
use App\Filament\Pages\Clearance\MyClearance;
use App\Filament\Pages\Clearance\PendingApprovals;
use App\Filament\Pages\Clearance\ViewClearance;
use Database\Seeders\Testing\TestDataset as T;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();
});

it('gives the provost of another hall a 403 when opening a request by URL, and the right provost the page', function () {
    $request = applyForClearance(T::STUDENT_ELIGIBLE);

    $this->actingAs(datasetUser(T::PROVOST_B))->get(ViewClearance::getUrl(['record' => $request->id]))->assertForbidden();

    $this->flushSession()->actingAs(datasetUser(T::PROVOST_A))->get(ViewClearance::getUrl(['record' => $request->id]))
        ->assertOk()->assertSee('Rakib Hasan')->assertSee('Approval timeline');
});

it('gives a student a 403 on another student\'s request', function () {
    $request = applyForClearance(T::STUDENT_ELIGIBLE);

    $this->actingAs(datasetUser(T::STUDENT_NON_RESIDENT))->get(ViewClearance::getUrl(['record' => $request->id]))->assertForbidden();
});

it('lists the waiting requests only to the approvers they belong to', function () {
    applyForClearance(T::STUDENT_ELIGIBLE);

    $this->actingAs(datasetUser(T::PROVOST_A));
    Livewire::test(PendingApprovals::class)->assertSee('Rakib Hasan');

    $this->actingAs(datasetUser(T::PROVOST_B));
    Livewire::test(PendingApprovals::class)->assertDontSee('Rakib Hasan')->assertSee('Nothing is waiting for you');
});

it('filters the waiting list by search text and department', function () {
    applyForClearance(T::STUDENT_ELIGIBLE);
    applyForClearance(T::STUDENT_LIBRARY_LOAN);
    approveInOrder(App\Models\ClearanceRequest::query()->orderBy('id')->first(), [T::PROVOST_A]);
    approveInOrder(App\Models\ClearanceRequest::query()->orderByDesc('id')->first(), [T::PROVOST_B]);

    $this->actingAs(datasetUser(T::LIBRARIAN));
    Livewire::test(PendingApprovals::class)->assertSee('Rakib Hasan')->assertSee('Tasnim Sultana')
        ->set('search', 'rakib')->assertSee('Rakib Hasan')->assertDontSee('Tasnim Sultana')
        ->set('search', '')->set('departmentId', App\Models\Department::query()->where('code', T::DEPT_EEE)->value('id'))
        ->assertSee('Tasnim Sultana')->assertDontSee('Rakib Hasan');
});

it('lets the approver approve and reject from the request page', function () {
    $request = applyForClearance(T::STUDENT_ELIGIBLE);

    $this->actingAs(datasetUser(T::PROVOST_A));
    Livewire::test(ViewClearance::class, ['record' => $request->id])
        ->callAction('approve', ['remarks' => 'Room clear'])
        ->assertHasNoActionErrors();

    expect($request->fresh()->currentStage->key)->toBe('library');

    $this->actingAs(datasetUser(T::LIBRARIAN));
    Livewire::test(ViewClearance::class, ['record' => $request->id])
        ->callAction('reject', ['reason' => 'Return books'])
        ->assertHasNoActionErrors();

    expect($request->fresh()->status->value)->toBe('rejected');
});

it('hides the decision buttons once the request moved on', function () {
    $request = applyForClearance(T::STUDENT_ELIGIBLE);
    $this->actingAs(datasetUser(T::PROVOST_A));

    $page = Livewire::test(ViewClearance::class, ['record' => $request->id]);
    app(App\Services\Clearance\ClearanceService::class)->reject(datasetUser(T::PROVOST_A), $request->id, 'Wrong');

    $page->call('$refresh')->assertActionHidden('approve')->assertActionHidden('reject');

    expect($request->fresh()->status->value)->toBe('rejected')->and($request->approvals()->where('decision', 'approved')->count())->toBe(0);
});

it('shows the student the apply page with eligibility reasons, or the success state', function () {
    $this->actingAs(datasetUser(T::STUDENT_UNFINISHED));
    Livewire::test(Apply::class)->assertSee('You cannot apply yet')->assertSee('You have earned 4.5 of the 12 credits');

    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE));
    Livewire::test(Apply::class)->assertSee('You are eligible to apply for clearance')->callAction('submit');

    expect(App\Models\ClearanceRequest::query()->count())->toBe(1);
});

it('shows the student the live timeline and lets them resubmit after a rejection', function () {
    $request = applyForClearance(T::STUDENT_ELIGIBLE);
    app(App\Services\Clearance\ClearanceService::class)->reject(datasetUser(T::PROVOST_A), $request->id, 'Dues unpaid');

    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE));
    Livewire::test(MyClearance::class)->assertSee('Dues unpaid')->assertSee('Rejected')->callAction('resubmit');

    expect($request->fresh()->status->value)->toBe('pending');
});

it('hides the apply and approval pages from users who may not use them', function () {
    $this->actingAs(datasetUser(T::TEACHER));
    expect(Apply::canAccess())->toBeFalse()->and(PendingApprovals::canAccess())->toBeFalse();

    $this->actingAs(datasetUser(T::STUDENT_ELIGIBLE));
    expect(Apply::canAccess())->toBeTrue()->and(PendingApprovals::canAccess())->toBeFalse();
});
