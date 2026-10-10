<?php

use App\Http\Middleware\EnforceRoleTwoFactorSetup;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureTwoFactorChallengePassed;
use App\Http\Middleware\TrackUserSession;
use App\Models\UserSession;
use App\Services\Security\SessionTracker;
use Database\Seeders\Testing\TestDataset as T;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    seedTestDataset();
});

it('re-applies the session, 2FA, role-policy and profile-gate middleware on every Livewire update', function () {
    $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();

    expect($persistent)->toContain(TrackUserSession::class)
        ->toContain(EnsureTwoFactorChallengePassed::class)
        ->toContain(EnforceRoleTwoFactorSetup::class)
        ->toContain(EnsureProfileComplete::class);
});

it('aborts a Livewire request of a revoked session instead of returning a response Livewire would ignore', function () {
    $user = datasetUser(T::SUPER_ADMIN);
    $sessionId = str_repeat('b', 40);

    session()->setId($sessionId);
    session()->start();

    $record = UserSession::query()->create([
        'user_id' => $user->id,
        'session_hash' => hash('sha256', $sessionId),
        'device_label' => 'Chrome on Linux',
        'device_type' => 'desktop',
        'browser' => 'Chrome',
        'os' => 'Linux',
        'ip' => '127.0.0.1',
        'last_active_at' => now(),
        'expires_at' => now()->addHour(),
    ]);
    app(SessionTracker::class)->revoke($record, null, 'test');

    Auth::guard('web')->setUser($user);

    $request = Request::create('/', 'POST', server: ['HTTP_X_LIVEWIRE' => '1']);
    $request->setLaravelSession(session()->driver());
    $request->setUserResolver(fn () => $user);

    try {
        app(TrackUserSession::class)->handle($request, fn () => response('should not run'));
        $status = null;
    } catch (HttpException $exception) {
        $status = $exception->getStatusCode();
    }

    expect($status)->toBe(419);
});
