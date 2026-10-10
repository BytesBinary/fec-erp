<?php

use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
});

it('has no critical or serious accessibility issues on the student pages', function () {
    $page = uiLogin(T::STUDENT_ELIGIBLE);

    foreach (['/results', '/clearance/apply', '/security/devices', '/security/two-factor', '/profile/complete'] as $path) {
        $page->navigate($path)->wait(2)->assertNoAccessibilityIssues();
    }

    $page->navigate('/results')->click('#assistant-toggle')->wait(1)->assertNoAccessibilityIssues();
});

it('has no critical or serious accessibility issues on the clearance pages for staff', function () {
    $request = applyForClearance();

    $provost = uiLogin(T::PROVOST_A);
    foreach (['/clearance/pending', "/clearance/requests/{$request->id}", '/profile/signature', '/campus/hall-residents'] as $path) {
        $provost->navigate($path)->wait(2)->assertNoAccessibilityIssues();
    }

    $ready = approveInOrder($request, [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);

    $office = uiLogin(T::ADMIN_OFFICE);
    foreach (['/clearance/desk', "/clearance/requests/{$ready->id}", "/clearance/{$ready->id}/print"] as $path) {
        $office->navigate($path)->wait(2)->assertNoAccessibilityIssues();
    }

    visit('/verify/clearance/'.$ready->verify_code)->assertNoAccessibilityIssues();
});

it('has no critical or serious accessibility issues on the AI integration and security admin pages', function () {
    $page = uiLogin(T::SUPER_ADMIN);

    foreach (['/security/mcp-integrations', '/security/two-factor-policy', '/settings/ai-integrations', '/settings/profile-fields', '/settings/clearance-stages'] as $path) {
        $page->navigate($path)->wait(2)->assertNoAccessibilityIssues();
    }
});

it('has no critical or serious accessibility issues on the portal and email admin pages', function () {
    $page = uiLogin(T::SUPER_ADMIN);

    foreach (['/portal-monitor', '/student-results', '/email-notifications', '/email-templates', '/email-deliveries'] as $path) {
        $page->navigate($path)->wait(2)->assertNoAccessibilityIssues();
    }
});

it('has no critical or serious accessibility issues in the AI integrations wizard', function () {
    ['token' => $unused] = mcpIntegrationFor(datasetUser(T::TEACHER));

    $page = uiLogin(T::TEACHER)->assertSee('Two-factor verification');
    $page->fill('input[autocomplete=one-time-code]', totpCode('JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 1))->click('Verify')->wait(2);

    $page->navigate('/settings/ai-integrations')->wait(2)->assertNoAccessibilityIssues();
    $page->click('#start-wizard')->wait(2)->assertNoAccessibilityIssues();
});
