<?php

use App\Models\ClearanceRequest;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
});

/**
 * Opens "Waiting for me", reviews the request of `$studentName` and approves (or rejects) it.
 */
function reviewAs(string $email, string $studentName, string $decision = 'approve', string $text = ''): Pest\Browser\Api\AwaitableWebpage
{
    $page = uiLogin($email);
    $page->navigate('/clearance/pending')->assertSee('Clearance requests waiting for me')->assertSee($studentName);
    $page->click('Review')->wait(2)->assertSee($studentName);

    if ($decision === 'approve') {
        $page->click('Approve')->wait(1);

        if ($text !== '') {
            $page->fill('[id="mountedActionSchema0.remarks"]', $text);
        }

        $page->click('Confirm')->wait(3);
    } else {
        $page->click('Reject')->wait(1)->fill('[id="mountedActionSchema0.reason"]', $text)->click('Submit')->wait(3);
    }

    return $page;
}

it('E2E 3: student applies, four approvers approve, the office prints and hands over, the student sees Collected', function () {
    $student = uiLogin(T::STUDENT_ELIGIBLE);
    $student->navigate('/clearance/apply')->assertSee('You are eligible to apply for clearance');
    $student->click('Submit clearance request')->wait(1)->click('Confirm')->wait(3);

    $student->assertPathIs('/clearance/my')->assertSee('In progress')->assertSeeIn('[data-testid=request-no]', 'CLR-');
    $student->assertCount('[data-stage=hall][data-status=pending]', 1);

    reviewAs(T::PROVOST_A, 'Rakib Hasan')->assertPathIs('/clearance/pending')->assertSee('Nothing is waiting for you');
    reviewAs(T::LIBRARIAN, 'Rakib Hasan', text: 'No dues');
    reviewAs(T::DEPT_HEAD_CSE, 'Rakib Hasan');
    reviewAs(T::HEAD_OF_INSTITUTION, 'Rakib Hasan');

    $student->navigate('/clearance/my');
    $student->assertSeeIn('[data-testid=clearance-status]', 'Ready for collection')
        ->assertSeeIn('[data-testid=ready-message]', 'Visit the Administration Office once, with your student ID');
    $student->assertCount('[data-status=approved]', 4);

    $office = uiLogin(T::ADMIN_OFFICE);
    $office->navigate('/clearance/desk')->assertSee('Clearance desk')->assertSee('Rakib Hasan');
    $office->fill('#desk-search', 'CSE-21-003')->wait(1)->assertSee('Rakib Hasan');
    $office->click('a[href*="/clearance/requests/"]')->wait(2)->assertSee('Approval timeline');

    $request = ClearanceRequest::query()->firstOrFail();
    $office->navigate("/clearance/{$request->id}/print");
    $office->assertCount('[data-testid=signature-image]', 4)
        ->assertPresent('[data-testid=qr-code]')
        ->assertPresent('[data-testid=seal-area]')
        ->assertSee('Valid only with the Principal');
    expect($office->script("document.querySelector('[data-testid=principal-box]').textContent.trim()"))->toBe('');

    $office->navigate("/clearance/requests/{$request->id}")->click('Print')->wait(3);
    $office->assertPathBeginsWith("/clearance/{$request->id}/print")->assertSee('Original copy');

    $office->navigate("/clearance/requests/{$request->id}")->assertSee('Print history');
    $office->click('Mark collected')->wait(1)->check('[id="mountedActionSchema0.id_verified"]')->click('Submit')->wait(3);
    $office->assertSee('Collected');

    $student->navigate('/clearance/my')->assertSeeIn('[data-testid=clearance-status]', 'Collected')
        ->assertSeeIn('[data-testid=collected-message]', 'has been collected');
});

it('E2E 4: a rejection shows the reason, the student resubmits and the request returns to the rejecting stage', function () {
    $student = uiLogin(T::STUDENT_ELIGIBLE);
    $student->navigate('/clearance/apply')->click('Submit clearance request')->wait(1)->click('Confirm')->wait(3);

    reviewAs(T::PROVOST_A, 'Rakib Hasan');
    reviewAs(T::LIBRARIAN, 'Rakib Hasan', 'reject', 'Return 2 library books');

    $student->navigate('/clearance/my')
        ->assertSeeIn('[data-testid=clearance-status]', 'Rejected')
        ->assertSeeIn('[data-testid=stage-remarks]', 'Return 2 library books')
        ->assertCount('[data-stage=library][data-status=rejected]', 1);

    $student->click('Resubmit')->wait(3);
    $student->navigate('/clearance/my')
        ->assertSeeIn('[data-testid=clearance-status]', 'In progress')
        ->assertCount('[data-stage=library][data-status=pending]', 1)
        ->assertCount('[data-stage=hall][data-status=approved]', 1);

    reviewAs(T::LIBRARIAN, 'Rakib Hasan');
    reviewAs(T::DEPT_HEAD_CSE, 'Rakib Hasan');
    reviewAs(T::HEAD_OF_INSTITUTION, 'Rakib Hasan');

    $student->navigate('/clearance/my')->assertSeeIn('[data-testid=clearance-status]', 'Ready for collection');
});

it('E2E 5: a non-residential student has the hall stage skipped and the chain starts at the library', function () {
    $student = uiLogin(T::STUDENT_NON_RESIDENT);
    $student->navigate('/clearance/apply')->click('Submit clearance request')->wait(1)->click('Confirm')->wait(3);

    $student->assertPathIs('/clearance/my')
        ->assertCount('[data-stage=hall][data-status=skipped]', 1)
        ->assertCount('[data-stage=library][data-status=pending]', 1);

    $provost = uiLogin(T::PROVOST_A);
    $provost->navigate('/clearance/pending')->assertSee('Nothing is waiting for you');

    reviewAs(T::LIBRARIAN, 'Nusrat Jahan');
    reviewAs(T::DEPT_HEAD_CSE, 'Nusrat Jahan');
    reviewAs(T::HEAD_OF_INSTITUTION, 'Nusrat Jahan');

    $student->navigate('/clearance/my')->assertSeeIn('[data-testid=clearance-status]', 'Ready for collection');
});

it('E2E 6: a provost of another hall neither sees the request nor can open its URL', function () {
    $request = applyForClearance(T::STUDENT_ELIGIBLE);

    $other = uiLogin(T::PROVOST_B);
    $other->navigate('/clearance/pending')->assertSee('Nothing is waiting for you')->assertDontSee('Rakib Hasan');

    $other->navigate("/clearance/requests/{$request->id}")->assertSee('403')->assertDontSee('Rakib Hasan');

    $owner = uiLogin(T::PROVOST_A);
    $owner->navigate("/clearance/requests/{$request->id}")->assertSee('Rakib Hasan')->assertSee('Approval timeline');
});

it('shows a provost the hall dues and a librarian the outstanding books of the student', function () {
    applyForClearance(T::STUDENT_LIBRARY_LOAN);

    $provost = uiLogin(T::PROVOST_B);
    $provost->navigate('/clearance/pending')->click('Review')->wait(2)->assertSee('Hall dues')->assertSee('No dues recorded');
    $request = ClearanceRequest::query()->firstOrFail();

    app(App\Services\Clearance\ClearanceService::class)->approve(datasetUser(T::PROVOST_B), $request->id);

    $librarian = uiLogin(T::LIBRARIAN);
    $librarian->navigate('/clearance/pending')->click('Review')->wait(2)
        ->assertSeeIn('[data-testid=library-summary]', 'Outstanding books: 1')
        ->assertSee('Network Analysis and Synthesis')
        ->assertSee('(not returned)');
});

/**
 * Approves a freshly applied request through the services and returns it ready for collection.
 */
function readyForDesk(string $student = T::STUDENT_ELIGIBLE): ClearanceRequest
{
    return approveInOrder(applyForClearance($student), [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);
}

it('E2E 7: the QR link opens a valid verification page while logged out; a tampered record shows the integrity warning', function () {
    $request = readyForDesk();

    $office = uiLogin(T::ADMIN_OFFICE);
    $office->navigate("/clearance/{$request->id}/print");
    $url = $office->attribute('[data-testid=qr-code]', 'data-verify-url');
    $path = parse_url((string) $url, PHP_URL_PATH);

    expect($path)->toStartWith('/verify/clearance/')->not->toContain($request->request_no);

    $public = visit($path);
    $public->assertSeeIn('[data-testid=verification-status]', 'Valid')
        ->assertSeeIn('[data-testid=verify-student]', 'Rakib Hasan')
        ->assertSeeIn('[data-testid=verify-integrity]', 'Intact')
        ->assertDontSee('Abdul Karim');

    App\Models\ClearanceApproval::query()->where('decision', 'approved')->orderBy('id')->first()->update(['approver_name' => 'Forged Signer']);

    $public->navigate($path)->assertSeeIn('[data-testid=verification-status]', 'WARNING: this record has been altered')
        ->assertSeeIn('[data-testid=verify-integrity]', 'ALTERED');
});

it('E2E 8: a second print is marked DUPLICATE and logged', function () {
    $request = readyForDesk();

    $office = uiLogin(T::ADMIN_OFFICE);
    $office->navigate("/clearance/requests/{$request->id}")->click('Print')->wait(3);
    $office->assertSee('Original copy')->assertNotPresent('[data-testid=duplicate-watermark]');

    $office->navigate("/clearance/requests/{$request->id}")->assertSee('Reprint as duplicate')->click('Reprint as duplicate')->wait(3);
    $office->assertSee('DUPLICATE copy')->assertPresent('[data-testid=duplicate-watermark]');

    $office->navigate("/clearance/requests/{$request->id}");
    $office->assertCount('[data-testid=print-history] li', 2)->assertCount('[data-testid=print-history] li[data-duplicate="1"]', 1);

    expect($request->prints()->count())->toBe(2)
        ->and($request->prints()->where('is_duplicate', true)->count())->toBe(1);
});
