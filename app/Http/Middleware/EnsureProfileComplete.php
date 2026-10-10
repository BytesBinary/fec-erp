<?php

namespace App\Http\Middleware;

use App\Exceptions\Domain\ProfileIncompleteException;
use App\Filament\Pages\Profile\CompleteProfile;
use App\Models\User;
use App\Services\Profile\ProfileService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profile gate (spec §6): a student with an incomplete profile can only reach
 * the profile page and logout; JSON clients get PROFILE_INCOMPLETE.
 */
class EnsureProfileComplete
{
    public function __construct(protected ProfileService $profiles) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $this->profiles->isGated($user)) {
            return $next($request);
        }

        if ($request->routeIs(CompleteProfile::getRouteName(), 'filament.erp.auth.logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            throw new ProfileIncompleteException;
        }

        if ($request->hasHeader('X-Livewire')) {
            abort(419);
        }

        return redirect()->to(CompleteProfile::getUrl());
    }
}
