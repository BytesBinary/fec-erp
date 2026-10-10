<?php

use App\Models\ClearanceApproval;
use App\Services\Clearance\HashChain;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedTestDataset();
    $this->chain = new HashChain;
    $this->request = approveInOrder(applyForClearance(), [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);
});

it('is intact for an untouched clearance', function () {
    expect($this->chain->verify($this->request->fresh())['intact'])->toBeTrue();
});

it('detects a changed approval field', function () {
    ClearanceApproval::query()->where('decision', 'approved')->orderBy('id')->first()->update(['remarks' => 'Forged']);

    expect($this->chain->verify($this->request->fresh()))->toMatchArray(['intact' => false]);
});

it('detects a deleted approval', function () {
    ClearanceApproval::query()->where('decision', 'approved')->orderByDesc('id')->first()->delete();

    expect($this->chain->verify($this->request->fresh())['intact'])->toBeFalse();
});

it('detects a swapped signature image even though the approval row is untouched', function () {
    $approval = ClearanceApproval::query()->where('decision', 'approved')->orderBy('id')->firstOrFail();

    Storage::disk('local')->put($approval->signature_snapshot_path, "\x89PNG\r\n\x1a\nforged");

    expect($this->chain->verify($this->request->fresh()))->toMatchArray(['intact' => false, 'broken_at' => $approval->id]);
});

it('detects a missing signature image', function () {
    $approval = ClearanceApproval::query()->where('decision', 'approved')->orderBy('id')->firstOrFail();

    Storage::disk('local')->delete($approval->signature_snapshot_path);

    expect($this->chain->verify($this->request->fresh())['intact'])->toBeFalse();
});
