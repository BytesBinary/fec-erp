<?php

use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    seedTestDataset();
});

function notificationTitles(string $email): array
{
    return datasetUser($email)->notifications()->get()->pluck('data.title')->all();
}

it('reminds the approver of a request that waited longer than the threshold, once', function () {
    applyForClearance();
    $before = count(notificationTitles(T::PROVOST_A));

    $this->travel(2)->days();
    $this->artisan('clearance:remind-pending')->assertSuccessful();
    expect(count(notificationTitles(T::PROVOST_A)))->toBe($before);

    $this->travel(2)->days();
    $this->artisan('clearance:remind-pending')->expectsOutputToContain('Reminded 1 request(s), escalated 0')->assertSuccessful();

    expect(notificationTitles(T::PROVOST_A))->toContain('Clearance request waiting for you')
        ->and(notificationTitles(T::SUPER_ADMIN))->not->toContain('Clearance request waiting too long');

    $this->artisan('clearance:remind-pending')->expectsOutputToContain('Reminded 0 request(s)')->assertSuccessful();
});

it('escalates to super admin after the escalation threshold', function () {
    applyForClearance();

    $this->travel(8)->days();
    $this->artisan('clearance:remind-pending')->expectsOutputToContain('escalated 1')->assertSuccessful();

    expect(notificationTitles(T::SUPER_ADMIN))->toContain('Clearance request waiting too long')
        ->and(notificationTitles(T::PROVOST_A))->toContain('Clearance request waiting for you')
        ->and(notificationTitles(T::PROVOST_B))->not->toContain('Clearance request waiting for you');
});

it('does not remind for requests that are not waiting at a stage', function () {
    $ready = approveInOrder(applyForClearance(), [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);

    $this->travel(10)->days();
    $this->artisan('clearance:remind-pending')->expectsOutputToContain('Reminded 0 request(s)')->assertSuccessful();

    expect($ready->fresh()->last_reminded_at)->toBeNull();
});

it('sends the administration office a digest of requests ready for collection', function () {
    approveInOrder(applyForClearance(), [T::PROVOST_A, T::LIBRARIAN, T::DEPT_HEAD_CSE, T::HEAD_OF_INSTITUTION]);

    $this->artisan('clearance:remind-pending')->expectsOutputToContain('digest sent to 1')->assertSuccessful();

    expect(notificationTitles(T::ADMIN_OFFICE))->toContain('Clearances ready for collection');
});

it('sends no digest when nothing is ready', function () {
    Notification::fake();

    $this->artisan('clearance:remind-pending')->expectsOutputToContain('digest sent to 0')->assertSuccessful();

    Notification::assertNothingSent();
});

it('prunes old session records and schedules both commands daily', function () {
    $this->artisan('sessions:prune')->expectsOutputToContain('Pruned 0')->assertSuccessful();

    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($event) => $event->command)->implode(' ');

    expect($events)->toContain('clearance:remind-pending')->toContain('sessions:prune')->toContain('mcp:notify-expiring');
});
