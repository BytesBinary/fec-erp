<?php

use App\Exceptions\Domain\DomainException;
use App\Http\Middleware\EnforceRoleTwoFactorSetup;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\EnsureTwoFactorChallengePassed;
use App\Http\Middleware\TrackUserSession;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('erp.secure', [
            TrackUserSession::class,
            Authenticate::class,
            EnsureTwoFactorChallengePassed::class,
            EnforceRoleTwoFactorSetup::class,
            EnsureProfileComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (DomainException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $exception->toArray()], $exception->httpStatus());
            }

            abort($exception->httpStatus(), $exception->getMessage());
        });
    })->create();
