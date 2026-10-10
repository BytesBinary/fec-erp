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

it('E2E 3 (to ready): student applies and four approvers approve in order, then the student sees the ready message', function () {
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
