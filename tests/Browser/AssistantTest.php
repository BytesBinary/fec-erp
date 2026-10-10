<?php

use App\Models\Department;
use App\Services\Assistant\Providers\FakeProvider;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
    config(['assistant.provider' => 'fake', 'assistant.stream' => false]);
    FakeProvider::reset();
});

it('E2E 9: the student opens the widget, asks where the results are, clicks the deep link and lands on the Result page', function () {
    $page = uiLogin(T::STUDENT_ELIGIBLE);

    $page->assertPresent('#assistant-toggle')->assertNotPresent('#assistant-panel:not([style*="display: none"])');
    $page->click('#assistant-toggle')->wait(1)->assertSee('Ask where to find something');

    $page->fill('#assistant-input', 'where are my results')->click('#assistant-send')->wait(3);

    $page->assertSeeIn('#assistant-panel', 'where are my results')
        ->assertSeeIn('#assistant-panel', 'You can find it under Result')
        ->assertPresent('[data-testid=assistant-link]');

    $page->click('[data-testid=assistant-link]')->wait(3)->assertPathIs('/results')->assertSee('CGPA');
});

it('shows a confirmation card for a write and only changes data after Confirm', function () {
    $page = uiLogin(T::SUPER_ADMIN);

    $page->click('#assistant-toggle')->wait(1)
        ->fill('#assistant-input', 'create a department called Zoology code ZOO')->click('#assistant-send')->wait(3);

    $page->assertPresent('[data-testid=assistant-confirm]')->assertSeeIn('[data-testid=assistant-confirm]', 'Zoology');
    expect(Department::query()->where('code', 'ZOO')->exists())->toBeFalse();

    $page->click('[data-testid=assistant-confirm-yes]')->wait(2)->assertSeeIn('[data-testid=assistant-confirm-result]', 'Done');

    expect(Department::query()->where('code', 'ZOO')->exists())->toBeTrue();
});

it('tells a student the course screen is not available to their role', function () {
    $page = uiLogin(T::STUDENT_ELIGIBLE);

    $page->click('#assistant-toggle')->wait(1)->fill('#assistant-input', 'where do I create a course?')->click('#assistant-send')->wait(3);

    $page->assertSeeIn('#assistant-panel', 'not available to your role')->assertNotPresent('[data-testid=assistant-link]');
});
