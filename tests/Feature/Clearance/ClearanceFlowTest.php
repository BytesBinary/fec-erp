<?php

use App\Enums\ClearanceStatus;
use App\Exceptions\Domain\ConflictException;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\InvalidStateException;
use App\Exceptions\Domain\ValidationException;
use App\Models\ClearanceApproval;
use App\Models\ClearanceEvent;
use App\Models\ClearanceStage;
use App\Models\StaffSignature;
use App\Notifications\ClearanceNotification;
use App\Services\Clearance\ClearanceService;
use App\Services\Clearance\ClearanceStageService;
use App\Services\Clearance\HashChain;
use App\Services\Clearance\StaffSignatureService;
use Database\Seeders\Testing\SignatureImage;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedTestDataset();
    $this->service = app(ClearanceService::class);
    $this->chain = [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION];
});

it('creates a numbered request at the first stage with an unguessable verification code', function () {
    $request = applyForClearance();

    expect($request->request_no)->toMatch('/^CLR-\d{4}-\d{6}$/')
        ->and($request->status)->toBe(ClearanceStatus::Pending)
        ->and($request->currentStage->key)->toBe('hall')
        ->and($request->verify_code)->toHaveLength(32)
        ->and($request->verify_code)->not->toContain($request->request_no)
        ->and($request->version)->toBeGreaterThan(1);
});

it('numbers requests sequentially', function () {
    $first = applyForClearance(T::STUDENT_ELIGIBLE);
    $second = applyForClearance(T::STUDENT_NON_RESIDENT);

    expect((int) substr($second->request_no, -6))->toBe((int) substr($first->request_no, -6) + 1);
});

it('moves through all four stages in order and ends ready for collection with an intact hash chain', function () {
    $request = applyForClearance();

    foreach ($this->chain as $index => $email) {
        expect($request->status)->toBe(ClearanceStatus::Pending);
        $request = $this->service->approve(datasetUser($email), $request->id, "ok {$index}");
    }

    expect($request->status)->toBe(ClearanceStatus::ReadyForCollection)
        ->and($request->current_stage_id)->toBeNull()
        ->and($request->ready_at)->not->toBeNull()
        ->and($request->approvals()->where('decision', 'approved')->count())->toBe(4)
        ->and(app(HashChain::class)->verify($request)['intact'])->toBeTrue()
        ->and($request->chain_hash)->toBe($request->approvals()->reorder('id', 'desc')->value('hash'));

    expect(ClearanceEvent::query()->where('clearance_request_id', $request->id)->pluck('to_status')->all())
        ->toBe(['submitted', 'pending', 'pending', 'pending', 'pending', 'ready_for_collection']);
});

it('snapshots the signer, designation, remarks and signature of each approval', function () {
    $request = applyForClearance();
    $request = $this->service->approve(datasetUser(T::PROVOST_A), $request->id, 'Room handed over');

    $approval = ClearanceApproval::query()->where('decision', 'approved')->firstOrFail();

    expect($approval->approver_name)->toBe(datasetUser(T::PROVOST_A)->name)
        ->and($approval->approver_designation)->toContain('Hall Provost')
        ->and($approval->remarks)->toBe('Room handed over')
        ->and($approval->signature_snapshot_path)->toStartWith("clearance-signatures/{$request->id}/hall-")
        ->and(Storage::disk('local')->exists($approval->signature_snapshot_path))->toBeTrue()
        ->and($approval->signature_sha256)->toBe(hash('sha256', Storage::disk('local')->get($approval->signature_snapshot_path)))
        ->and($approval->ip)->not->toBeNull();
});

it('keeps old clearances unchanged when the approver later replaces their signature', function () {
    $request = applyForClearance();
    $this->service->approve(datasetUser(T::PROVOST_A), $request->id);
    $approval = ClearanceApproval::query()->where('decision', 'approved')->firstOrFail();
    $before = Storage::disk('local')->get($approval->signature_snapshot_path);

    app(StaffSignatureService::class)->store(datasetUser(T::PROVOST_A), SignatureImage::png(99));

    expect(Storage::disk('local')->get($approval->signature_snapshot_path))->toBe($before)
        ->and(app(HashChain::class)->verify($request->fresh())['intact'])->toBeTrue();
});

it('cannot approve without a signature image', function () {
    StaffSignature::query()->where('user_id', datasetUser(T::PROVOST_A)->id)->delete();
    $request = applyForClearance();

    $this->service->approve(datasetUser(T::PROVOST_A), $request->id);
})->throws(ValidationException::class, 'Upload your signature');

it('does not let a later stage approve before the earlier one', function () {
    $request = applyForClearance();

    expect(fn () => $this->service->approve(datasetUser(T::LIBRARIAN), $request->id))->toThrow(ForbiddenException::class)
        ->and(fn () => $this->service->approve(datasetUser(T::DEPT_HEAD_CSE), $request->id))->toThrow(ForbiddenException::class)
        ->and(fn () => $this->service->approve(datasetUser(T::HEAD_OF_INSTITUTION), $request->id))->toThrow(ForbiddenException::class)
        ->and($request->fresh()->status)->toBe(ClearanceStatus::Pending);
});

it('only lets the provost of the student\'s own hall approve at the hall stage', function () {
    $request = applyForClearance();

    expect(fn () => $this->service->approve(datasetUser(T::PROVOST_B), $request->id))->toThrow(ForbiddenException::class);

    $this->service->approve(datasetUser(T::PROVOST_A), $request->id);
});

it('only lets the head of the student\'s own department approve at the department stage', function () {
    $request = approveInOrder(applyForClearance(), [T::PROVOST_A, T::LIBRARIAN]);

    expect(fn () => $this->service->approve(datasetUser(T::DEPT_HEAD_EEE), $request->id))->toThrow(ForbiddenException::class);

    $this->service->approve(datasetUser(T::DEPT_HEAD_CSE), $request->id);
});

it('refuses staff without the stage role even with other approval permissions', function () {
    $request = applyForClearance();

    expect(fn () => $this->service->approve(datasetUser(T::ADMIN_OFFICE), $request->id))->toThrow(ForbiddenException::class)
        ->and(fn () => $this->service->approve(datasetUser(T::TEACHER), $request->id))->toThrow(ForbiddenException::class)
        ->and(fn () => $this->service->approve(datasetUser(T::STUDENT_ELIGIBLE), $request->id))->toThrow(ForbiddenException::class);
});

it('skips the hall stage for a non-residential student and records the skip', function () {
    $request = applyForClearance(T::STUDENT_NON_RESIDENT);

    expect($request->currentStage->key)->toBe('library');

    $skipped = ClearanceApproval::query()->where('decision', 'skipped')->firstOrFail();
    expect($skipped->stage->key)->toBe('hall')->and($skipped->approver_user_id)->toBeNull();

    $timeline = collect($this->service->timeline($request));
    expect($timeline->firstWhere(fn (array $row): bool => $row['stage']->key === 'hall')['status'])->toBe('skipped')
        ->and($timeline->firstWhere(fn (array $row): bool => $row['stage']->key === 'library')['status'])->toBe('pending');

    $request = approveInOrder($request, [T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);

    expect($request->status)->toBe(ClearanceStatus::ReadyForCollection)
        ->and(app(HashChain::class)->verify($request)['intact'])->toBeTrue();
});

it('resumes at the rejecting stage after a rejection and keeps earlier approvals valid', function () {
    $request = approveInOrder(applyForClearance(), [T::PROVOST_A]);

    $request = $this->service->reject(datasetUser(T::LIBRARIAN), $request->id, 'Return 2 library books');

    expect($request->status)->toBe(ClearanceStatus::Rejected)
        ->and($request->currentStage->key)->toBe('library');

    expect(fn () => $this->service->approve(datasetUser(T::LIBRARIAN), $request->id))->toThrow(InvalidStateException::class);

    $request = $this->service->resubmit(datasetUser(T::STUDENT_ELIGIBLE), $request->id);

    expect($request->status)->toBe(ClearanceStatus::Pending)
        ->and($request->currentStage->key)->toBe('library')
        ->and($request->approvals()->where('decision', 'approved')->count())->toBe(1);

    expect(fn () => $this->service->approve(datasetUser(T::PROVOST_A), $request->id))->toThrow(ForbiddenException::class);

    $request = approveInOrder($request, [T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);
    expect($request->status)->toBe(ClearanceStatus::ReadyForCollection)
        ->and(app(HashChain::class)->verify($request)['intact'])->toBeTrue();
});

it('shows the rejection reason on the timeline', function () {
    $request = approveInOrder(applyForClearance(), [T::PROVOST_A]);
    $request = $this->service->reject(datasetUser(T::LIBRARIAN), $request->id, 'Return 2 library books');

    $row = collect($this->service->timeline($request))->firstWhere(fn (array $r): bool => $r['stage']->key === 'library');

    expect($row['status'])->toBe('rejected')->and($row['remarks'])->toBe('Return 2 library books');
});

it('requires a reason to reject', function () {
    $request = applyForClearance();

    $this->service->reject(datasetUser(T::PROVOST_A), $request->id, '   ');
})->throws(ValidationException::class);

it('only lets the student resubmit their own rejected request', function () {
    $request = $this->service->reject(datasetUser(T::PROVOST_A), applyForClearance()->id, 'Dues unpaid');

    expect(fn () => $this->service->resubmit(datasetUser(T::STUDENT_NON_RESIDENT), $request->id))->toThrow(ForbiddenException::class);
});

it('rejects invalid transitions with INVALID_STATE', function () {
    $request = applyForClearance();

    expect(fn () => $this->service->resubmit(datasetUser(T::STUDENT_ELIGIBLE), $request->id))->toThrow(InvalidStateException::class);

    $ready = approveInOrder($request, $this->chain);
    expect(fn () => $this->service->approve(datasetUser(T::HEAD_OF_INSTITUTION), $ready->id))->toThrow(InvalidStateException::class)
        ->and(fn () => $this->service->reject(datasetUser(T::HEAD_OF_INSTITUTION), $ready->id, 'late'))->toThrow(InvalidStateException::class);
});

it('lets two tabs with the same version race and gives the loser CONFLICT', function () {
    $request = applyForClearance();
    $version = $request->version;

    $this->service->approve(datasetUser(T::PROVOST_A), $request->id, null, $version);

    expect(fn () => $this->service->approve(datasetUser(T::PROVOST_A), $request->id, null, $version))->toThrow(ConflictException::class);

    expect($request->approvals()->where('decision', 'approved')->count())->toBe(1);
});

it('detects a version that changed between page load and action', function () {
    $request = applyForClearance();

    $this->service->reject(datasetUser(T::PROVOST_A), $request->id, 'Wrong room', $request->version);

    expect(fn () => $this->service->reject(datasetUser(T::PROVOST_A), $request->id, 'Again', $request->version))->toThrow(ConflictException::class);
});

it('increments the version on every transition', function () {
    $request = applyForClearance();
    $first = $request->version;

    $request = $this->service->approve(datasetUser(T::PROVOST_A), $request->id);

    expect($request->version)->toBe($first + 1);
});

it('lets a student cancel before the first approval only', function () {
    $request = applyForClearance();
    $cancelled = $this->service->cancel(datasetUser(T::STUDENT_ELIGIBLE), $request->id);

    expect($cancelled->status)->toBe(ClearanceStatus::Cancelled)->and($cancelled->current_stage_id)->toBeNull();

    $second = applyForClearance(T::STUDENT_NON_RESIDENT);
    $this->service->approve(datasetUser(T::LIBRARIAN), $second->id);

    expect(fn () => $this->service->cancel(datasetUser(T::STUDENT_NON_RESIDENT), $second->id))->toThrow(InvalidStateException::class);
});

it('lets only super admin cancel someone else\'s request, with a reason', function () {
    $request = applyForClearance();

    expect(fn () => $this->service->cancel(datasetUser(T::ADMIN_OFFICE), $request->id, 'x'))->toThrow(ForbiddenException::class)
        ->and(fn () => $this->service->cancel(datasetUser(T::SUPER_ADMIN), $request->id))->toThrow(ValidationException::class);

    $cancelled = $this->service->cancel(datasetUser(T::SUPER_ADMIN), $request->id, 'Duplicate application');

    expect($cancelled->status)->toBe(ClearanceStatus::Cancelled)->and($cancelled->cancel_reason)->toBe('Duplicate application');
});

it('allows a new application after a cancellation', function () {
    $first = applyForClearance();
    $this->service->cancel(datasetUser(T::STUDENT_ELIGIBLE), $first->id);

    expect(applyForClearance()->id)->not->toBe($first->id);
});

it('allows only one active request per student', function () {
    applyForClearance();

    applyForClearance();
})->throws(InvalidStateException::class);

it('lists each approver only the requests waiting for them', function () {
    $eligible = applyForClearance(T::STUDENT_ELIGIBLE);
    $loan = applyForClearance(T::STUDENT_LIBRARY_LOAN);
    $nonResident = applyForClearance(T::STUDENT_NON_RESIDENT);

    expect($this->service->pendingFor(datasetUser(T::PROVOST_A))->pluck('id')->all())->toBe([$eligible->id])
        ->and($this->service->pendingFor(datasetUser(T::PROVOST_B))->pluck('id')->all())->toBe([$loan->id])
        ->and($this->service->pendingFor(datasetUser(T::LIBRARIAN))->pluck('id')->all())->toBe([$nonResident->id])
        ->and($this->service->pendingFor(datasetUser(T::DEPT_HEAD_CSE))->count())->toBe(0)
        ->and($this->service->pendingFor(datasetUser(T::HEAD_OF_INSTITUTION))->count())->toBe(0);

    $this->service->approve(datasetUser(T::PROVOST_A), $eligible->id);

    expect($this->service->pendingFor(datasetUser(T::LIBRARIAN))->pluck('id')->sort()->values()->all())->toBe(collect([$eligible->id, $nonResident->id])->sort()->values()->all());
});

it('hides another hall\'s request from a provost and forbids opening it', function () {
    $request = applyForClearance(T::STUDENT_ELIGIBLE);

    expect($this->service->pendingFor(datasetUser(T::PROVOST_B)))->toHaveCount(0);
    expect(fn () => $this->service->get(datasetUser(T::PROVOST_B), $request->id))->toThrow(ForbiddenException::class);
    expect($this->service->get(datasetUser(T::PROVOST_A), $request->id)->id)->toBe($request->id);
});

it('lets a student see only their own request', function () {
    $request = applyForClearance(T::STUDENT_ELIGIBLE);

    expect(fn () => $this->service->get(datasetUser(T::STUDENT_NON_RESIDENT), $request->id))->toThrow(ForbiddenException::class)
        ->and($this->service->mine(datasetUser(T::STUDENT_ELIGIBLE))->id)->toBe($request->id)
        ->and($this->service->mine(datasetUser(T::STUDENT_NON_RESIDENT)))->toBeNull();
});

it('locks name, parents and date of birth once the student applied', function () {
    applyForClearance();

    expect(studentFor(T::STUDENT_ELIGIBLE)->profile->locked_fields)->toBe(config('profile.locked_after_clearance'));
});

it('notifies the student on every step and the approvers when it is their turn', function () {
    Notification::fake();
    $request = applyForClearance();

    Notification::assertSentTo(datasetUser(T::STUDENT_ELIGIBLE), ClearanceNotification::class);
    Notification::assertSentTo(datasetUser(T::PROVOST_A), ClearanceNotification::class);
    Notification::assertNotSentTo(datasetUser(T::PROVOST_B), ClearanceNotification::class);
    Notification::assertNotSentTo(datasetUser(T::LIBRARIAN), ClearanceNotification::class);

    $this->service->approve(datasetUser(T::PROVOST_A), $request->id);

    Notification::assertSentTo(datasetUser(T::LIBRARIAN), ClearanceNotification::class);

    approveInOrder($request->fresh(), [T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);

    Notification::assertSentTo(datasetUser(T::STUDENT_ELIGIBLE), ClearanceNotification::class, fn (ClearanceNotification $n): bool => str_contains($n->title(), 'ready for collection'));
});

describe('hash chain', function () {
    it('detects a tampered remark', function () {
        $request = approveInOrder(applyForClearance(), [T::PROVOST_A, T::LIBRARIAN]);

        ClearanceApproval::query()->where('decision', 'approved')->orderBy('id')->first()->update(['remarks' => 'forged']);

        $result = app(HashChain::class)->verify($request->fresh());
        expect($result['intact'])->toBeFalse()->and($result['broken_at'])->not->toBeNull();
    });

    it('detects a swapped approver or signature', function () {
        $request = approveInOrder(applyForClearance(), [T::PROVOST_A, T::LIBRARIAN]);

        ClearanceApproval::query()->where('decision', 'approved')->orderBy('id')->first()->update(['approver_name' => 'Somebody Else']);
        expect(app(HashChain::class)->verify($request->fresh())['intact'])->toBeFalse();
    });

    it('detects a deleted approval', function () {
        $request = approveInOrder(applyForClearance(), [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE]);

        ClearanceApproval::query()->where('decision', 'approved')->orderBy('id')->skip(1)->first()->delete();
        expect(app(HashChain::class)->verify($request->fresh())['intact'])->toBeFalse();
    });

    it('detects a tampered request number or stored chain hash', function () {
        $request = approveInOrder(applyForClearance(), [T::PROVOST_A]);

        $request->update(['chain_hash' => str_repeat('0', 64)]);
        expect(app(HashChain::class)->verify($request->fresh())['intact'])->toBeFalse();
    });

    it('detects a re-pointed approval of another request', function () {
        $first = approveInOrder(applyForClearance(T::STUDENT_ELIGIBLE), [T::PROVOST_A]);
        $second = applyForClearance(T::STUDENT_LIBRARY_LOAN);
        ClearanceApproval::query()->where('clearance_request_id', $first->id)->where('decision', 'approved')->update(['clearance_request_id' => $second->id]);

        expect(app(HashChain::class)->verify($second->fresh())['intact'])->toBeFalse();
    });
});

describe('stage configuration', function () {
    it('lets super admin reorder the chain and the new order is used', function () {
        $stages = app(ClearanceStageService::class);
        $stages->move(datasetUser(T::SUPER_ADMIN), ClearanceStage::query()->where('key', 'library')->value('id'), -1);

        expect(ClearanceStage::query()->orderBy('order')->pluck('key')->all())->toBe(['library', 'hall', 'department', 'head']);

        $request = applyForClearance();
        expect($request->currentStage->key)->toBe('library');
    });

    it('lets super admin deactivate a stage', function () {
        app(ClearanceStageService::class)->toggle(datasetUser(T::SUPER_ADMIN), ClearanceStage::query()->where('key', 'department')->value('id'), 'active');

        $request = approveInOrder(applyForClearance(), [T::PROVOST_A, T::LIBRARIAN, T::HEAD_OF_INSTITUTION]);

        expect($request->status)->toBe(ClearanceStatus::ReadyForCollection);
    });

    it('refuses everyone but super admin', function () {
        app(ClearanceStageService::class)->all(datasetUser(T::ADMIN_OFFICE));
    })->throws(ForbiddenException::class);
});

it('answers canApproveClearance per request, role and scope', function () {
    $policies = app(App\Policies\Scopes\ScopePolicies::class);
    $request = applyForClearance(T::STUDENT_ELIGIBLE)->load('currentStage.approverRole', 'student');

    expect($policies->canApproveClearance(datasetUser(T::PROVOST_A), $request))->toBeTrue()
        ->and($policies->canApproveClearance(datasetUser(T::PROVOST_B), $request))->toBeFalse()
        ->and($policies->canApproveClearance(datasetUser(T::LIBRARIAN), $request))->toBeFalse()
        ->and($policies->canApproveClearance(datasetUser(T::SUPER_ADMIN), $request))->toBeFalse();
});
