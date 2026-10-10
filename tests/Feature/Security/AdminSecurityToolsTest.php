<?php

use App\Events\TwoFactorDeactivated;
use App\Exceptions\Domain\ForbiddenException;
use App\Exceptions\Domain\ValidationException;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\SessionsRelationManager;
use App\Models\AuditLog;
use App\Models\UserSession;
use App\Notifications\TwoFactorReset;
use App\Services\Security\SessionTracker;
use App\Services\Security\TwoFactorService;
use App\Services\Security\UserSecurityService;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedTestDataset();

    $this->admin = datasetUser(T::SUPER_ADMIN);
    $this->target = datasetUser(T::TEACHER);
    $this->service = app(UserSecurityService::class);

    foreach (['a', 'b'] as $letter) {
        UserSession::query()->create([
            'user_id' => $this->target->id, 'session_hash' => hash('sha256', $letter), 'device_label' => 'Chrome on Windows', 'device_type' => 'desktop',
            'last_active_at' => now(), 'expires_at' => now()->addHours(12),
        ]);
    }
});

it('lets super admin view and revoke a user\'s sessions with a reason, audit-logged', function () {
    expect($this->service->sessionsOf($this->admin, $this->target))->toHaveCount(2);

    $session = UserSession::query()->where('session_hash', hash('sha256', 'a'))->firstOrFail();
    $this->service->revokeSession($this->admin, $session, 'Lost laptop');

    expect($session->fresh()->isRevoked())->toBeTrue()
        ->and($session->fresh()->revoked_by)->toBe($this->admin->id)
        ->and($session->fresh()->revoked_reason)->toBe('Lost laptop');

    $log = AuditLog::query()->where('action', 'session.revoked_by_admin')->firstOrFail();
    expect($log->actor_user_id)->toBe($this->admin->id)->and($log->after['reason'])->toBe('Lost laptop');
});

it('revokes every session of a user at once', function () {
    expect($this->service->revokeAllSessions($this->admin, $this->target, 'Account takeover'))->toBe(2)
        ->and(app(SessionTracker::class)->activeFor($this->target))->toHaveCount(0);
});

it('requires a reason', function () {
    $this->service->revokeAllSessions($this->admin, $this->target, '   ');
})->throws(ValidationException::class);

it('refuses everyone but super admin', function (string $email) {
    $this->service->revokeAllSessions(datasetUser($email), $this->target, 'because');
})->throws(ForbiddenException::class)->with([T::TEACHER, T::DEPT_HEAD_CSE, T::ADMIN_OFFICE, T::STUDENT_ELIGIBLE]);

it('resets 2FA after a reason: removes it, signs the user out everywhere, announces it and notifies', function () {
    Notification::fake();
    Event::fake([TwoFactorDeactivated::class]);
    enableTwoFactorFor($this->target);

    $this->service->resetTwoFactor($this->admin, $this->target, 'Lost phone');

    expect(app(TwoFactorService::class)->isEnabled($this->target))->toBeFalse()
        ->and(app(SessionTracker::class)->activeFor($this->target))->toHaveCount(0);

    Notification::assertSentTo($this->target, TwoFactorReset::class);
    Event::assertDispatched(TwoFactorDeactivated::class);

    $log = AuditLog::query()->where('action', 'two_factor.reset_by_admin')->firstOrFail();
    expect($log->after['reason'])->toBe('Lost phone');
});

it('refuses a 2FA reset without a reason or from a non-admin', function () {
    enableTwoFactorFor($this->target);

    expect(fn () => $this->service->resetTwoFactor($this->admin, $this->target, ''))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->resetTwoFactor(datasetUser(T::LIBRARIAN), $this->target, 'x'))->toThrow(ForbiddenException::class)
        ->and(app(TwoFactorService::class)->isEnabled($this->target))->toBeTrue();
});

it('exposes the tools on the admin user page only to super admin', function () {
    $this->actingAs($this->admin);
    enableTwoFactorFor($this->target);

    Livewire::test(EditUser::class, ['record' => $this->target->getRouteKey()])
        ->assertActionVisible('resetTwoFactor')
        ->assertActionVisible('logoutEverywhere')
        ->callAction('logoutEverywhere', ['reason' => 'Audit'])
        ->assertHasNoActionErrors();

    expect(app(SessionTracker::class)->activeFor($this->target))->toHaveCount(0);

    Livewire::test(EditUser::class, ['record' => $this->target->getRouteKey()])
        ->callAction('resetTwoFactor', ['reason' => 'Lost phone'])
        ->assertHasNoActionErrors();

    expect(app(TwoFactorService::class)->isEnabled($this->target))->toBeFalse();
});

it('lists a user\'s sessions in the relation manager and revokes one', function () {
    $this->actingAs($this->admin);
    $session = UserSession::query()->where('session_hash', hash('sha256', 'a'))->firstOrFail();

    Livewire::test(SessionsRelationManager::class, ['ownerRecord' => $this->target, 'pageClass' => EditUser::class])
        ->assertCanSeeTableRecords(UserSession::query()->where('user_id', $this->target->id)->get())
        ->callTableAction('revoke', $session, ['reason' => 'Suspicious'])
        ->assertHasNoTableActionErrors();

    expect($session->fresh()->isRevoked())->toBeTrue();
});

it('hides the sessions tab from non-admins', function () {
    $this->actingAs(datasetUser(T::TEACHER));

    expect(SessionsRelationManager::canViewForRecord($this->target, EditUser::class))->toBeFalse();
});
