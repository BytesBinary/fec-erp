<?php

use App\Events\McpIntegrationsStopRequested;
use App\Filament\Pages\Security\Devices;
use App\Models\KnownDevice;
use App\Models\User;
use App\Models\UserSession;
use App\Notifications\NewDeviceLogin;
use App\Notifications\PasswordChanged;
use App\Services\Security\SessionTracker;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const CHROME_WINDOWS = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36';
const FIREFOX_LINUX = 'Mozilla/5.0 (X11; Linux x86_64; rv:127.0) Gecko/20100101 Firefox/127.0';

/**
 * Sends a request as `$user` in the browser session `$sessionId`.
 */
function asSession(User $user, string $sessionId, string $agent = CHROME_WINDOWS): Tests\TestCase
{
    return test()
        ->flushSession()
        ->actingAs($user)
        ->withCredentials()
        ->withCookie(config('session.cookie'), $sessionId)
        ->withHeader('User-Agent', $agent);
}

function sessionHash(string $sessionId): string
{
    return hash('sha256', $sessionId);
}

beforeEach(function () {
    seedTestDataset();

    $this->user = datasetUser(T::SUPER_ADMIN);
    $this->sessionA = str_repeat('a', 40);
    $this->sessionB = str_repeat('b', 40);
});

it('creates a session record with device details on the first authenticated request', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();

    $record = UserSession::query()->where('session_hash', sessionHash($this->sessionA))->firstOrFail();

    expect($record->user_id)->toBe($this->user->id)
        ->and($record->device_label)->toBe('Chrome on Windows')
        ->and($record->device_type)->toBe('desktop')
        ->and($record->ip)->toBe('127.0.0.1')
        ->and($record->remember)->toBeFalse()
        ->and($record->expires_at->between(now()->addHours(11), now()->addHours(13)))->toBeTrue()
        ->and($record->session_hash)->not->toBe($this->sessionA);
});

it('uses the thirty day window when remember me was ticked', function () {
    asSession($this->user, $this->sessionA)->withSession(['erp.remember' => true])->get('/')->assertOk();

    $record = UserSession::query()->firstOrFail();

    expect($record->remember)->toBeTrue()
        ->and($record->expires_at->between(now()->addDays(29), now()->addDays(31)))->toBeTrue();
});

it('writes last_active_at at most once a minute', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    $first = UserSession::query()->firstOrFail()->last_active_at;

    $this->travel(30)->seconds();
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    expect(UserSession::query()->firstOrFail()->last_active_at->equalTo($first))->toBeTrue();

    $this->travel(40)->seconds();
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    expect(UserSession::query()->firstOrFail()->last_active_at->greaterThan($first))->toBeTrue();
});

it('rejects a revoked session on its very next request: redirect for pages, 401 for json, 419 for livewire', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();

    $record = UserSession::query()->firstOrFail();
    app(SessionTracker::class)->revoke($record, null, 'test');

    asSession($this->user, $this->sessionA)->get('/')->assertRedirect(route('filament.erp.auth.login'));

    asSession($this->user, $this->sessionA)->getJson('/')->assertUnauthorized()->assertJsonPath('error.code', 'SESSION_REVOKED');
});

it('rejects a revoked session on livewire updates with 419 so the page reloads to the login screen', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    app(SessionTracker::class)->revoke(UserSession::query()->firstOrFail(), null, 'test');

    asSession($this->user, $this->sessionA)
        ->withHeader('X-Livewire', 'true')
        ->get('/')
        ->assertStatus(419);
});

it('rejects an expired session and marks it revoked', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();

    $this->travel(13)->hours();

    asSession($this->user, $this->sessionA)->getJson('/')->assertUnauthorized()->assertJsonPath('error.code', 'SESSION_EXPIRED');

    expect(UserSession::query()->firstOrFail()->revoked_reason)->toBe('expired');
});

it('keeps a remembered session alive past twelve hours', function () {
    asSession($this->user, $this->sessionA)->withSession(['erp.remember' => true])->get('/')->assertOk();

    $this->travel(3)->days();

    asSession($this->user, $this->sessionA)->get('/')->assertOk();
});

it('blocks the session of a deactivated account immediately', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();

    $this->user->forceFill(['is_active' => false])->save();

    asSession($this->user, $this->sessionA)->getJson('/')->assertForbidden();
});

it('does not let one user ride on another user\'s session record', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    $other = datasetUser(T::LIBRARIAN);

    asSession($other, $this->sessionA)->getJson('/')->assertUnauthorized();
});

it('sends a new-device alert only for browsers not seen before', function () {
    Notification::fake();

    asSession($this->user, $this->sessionA, CHROME_WINDOWS)->get('/')->assertOk();
    Notification::assertSentToTimes($this->user, NewDeviceLogin::class, 1);

    asSession($this->user, $this->sessionB, CHROME_WINDOWS)->get('/')->assertOk();
    Notification::assertSentToTimes($this->user, NewDeviceLogin::class, 1);

    asSession($this->user, str_repeat('c', 40), FIREFOX_LINUX)->get('/')->assertOk();
    Notification::assertSentToTimes($this->user, NewDeviceLogin::class, 2);

    expect(KnownDevice::query()->where('user_id', $this->user->id)->count())->toBe(2);
});

it('links the new-device alert to the devices page and mentions device and ip', function () {
    asSession($this->user, $this->sessionA, FIREFOX_LINUX)->get('/')->assertOk();

    $notification = $this->user->notifications()->latest()->firstOrFail();

    expect($notification->data['body'])->toContain('Firefox on Linux')->toContain('127.0.0.1')
        ->and(json_encode($notification->data))->toContain(str_replace('/', '\\/', Devices::getUrl()));
});

it('revokes every other session when the password changes and keeps the current one', function () {
    asSession($this->user, $this->sessionB)->get('/')->assertOk();
    asSession($this->user, $this->sessionA)->get('/')->assertOk();

    $this->user->update(['password' => 'a-brand-new-password']);

    expect(UserSession::query()->where('session_hash', sessionHash($this->sessionA))->firstOrFail()->isRevoked())->toBeFalse()
        ->and(UserSession::query()->where('session_hash', sessionHash($this->sessionB))->firstOrFail()->revoked_reason)->toBe('password_changed');
});

it('tells the user that the password changed and how many other devices were signed out', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    asSession($this->user, $this->sessionB)->get('/')->assertOk();
    Notification::fake();

    $this->user->update(['password' => 'another-new-password']);

    Notification::assertSentTo($this->user, PasswordChanged::class, fn (PasswordChanged $notification): bool => $notification->signedOutDevices >= 1
        && str_contains($notification->body(), $notification->signedOutDevices.' other device(s)')
        && $notification->url() === Devices::getUrl());
});

it('stops revoked sessions of other devices after a password change while the acting device survives', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    asSession($this->user, $this->sessionB)->get('/')->assertOk();

    $this->actingAs($this->user);
    $this->withCookie(config('session.cookie'), $this->sessionA);

    Livewire::actingAs($this->user)->test(Devices::class);

    $tracker = app(SessionTracker::class);
    $revoked = $tracker->revokeOthers($this->user, sessionHash($this->sessionA), $this->user, 'password_changed');

    expect($revoked)->toBe(1)
        ->and(UserSession::query()->where('session_hash', sessionHash($this->sessionA))->firstOrFail()->isRevoked())->toBeFalse()
        ->and(UserSession::query()->where('session_hash', sessionHash($this->sessionB))->firstOrFail()->revoked_reason)->toBe('password_changed');
});

it('lists sessions newest activity first and prunes records past the retention period', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    $this->travel(5)->minutes();
    asSession($this->user, $this->sessionB)->get('/')->assertOk();

    $list = app(SessionTracker::class)->activeFor($this->user);
    expect($list->first()->session_hash)->toBe(sessionHash($this->sessionB));

    app(SessionTracker::class)->revokeAll($this->user, null, 'test');
    expect(app(SessionTracker::class)->activeFor($this->user))->toHaveCount(0);

    $this->travel(91)->days();
    expect(app(SessionTracker::class)->prune())->toBe(2)
        ->and(UserSession::query()->count())->toBe(0);
});

it('keeps revoked sessions for ninety days for audit', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    app(SessionTracker::class)->revokeAll($this->user, null, 'test');

    $this->travel(89)->days();

    expect(app(SessionTracker::class)->prune())->toBe(0);
});

it('shows only the user\'s own sessions on the devices page, marks this device and logs out another', function () {
    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    asSession($this->user, $this->sessionB, FIREFOX_LINUX)->get('/')->assertOk();
    asSession(datasetUser(T::ADMIN_OFFICE), str_repeat('z', 40))->get('/')->assertOk();

    $other = UserSession::query()->where('user_id', datasetUser(T::ADMIN_OFFICE)->id)->firstOrFail();

    $page = asSession($this->user, $this->sessionA)->get(Devices::getUrl())->assertOk();
    $page->assertSee('This device')->assertSee('Firefox on Linux');

    $mine = UserSession::query()->where('session_hash', sessionHash($this->sessionB))->firstOrFail();

    $component = Livewire::actingAs($this->user)->test(Devices::class);
    $component->callAction('logoutDevice', arguments: ['session' => $other->id]);
    expect($other->fresh()->isRevoked())->toBeFalse();

    $component->callAction('logoutDevice', arguments: ['session' => $mine->id]);
    expect($mine->fresh()->isRevoked())->toBeTrue()
        ->and($mine->fresh()->revoked_by)->toBe($this->user->id);
});

it('logs out all other devices and optionally asks for the AI integrations to be stopped', function () {
    Event::fake([McpIntegrationsStopRequested::class]);

    asSession($this->user, $this->sessionA)->get('/')->assertOk();
    asSession($this->user, $this->sessionB)->get('/')->assertOk();
    asSession($this->user, str_repeat('c', 40), FIREFOX_LINUX)->get('/')->assertOk();

    $this->withCookie(config('session.cookie'), $this->sessionA);

    Livewire::actingAs($this->user)->test(Devices::class)->callAction('logoutOthers', ['stop_integrations' => false]);
    Event::assertNotDispatched(McpIntegrationsStopRequested::class);

    Livewire::actingAs($this->user)->test(Devices::class)->callAction('logoutOthers', ['stop_integrations' => true]);
    Event::assertDispatched(McpIntegrationsStopRequested::class);
});
