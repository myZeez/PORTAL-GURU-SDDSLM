<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ChangePassword;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps accounts that still carry a starting password (see TeacherSeeder) on the
 * change-password page until they pick their own.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()?->must_change_password
            && ! $request->routeIs(ChangePassword::getRouteName(), 'filament.portal.auth.logout')
        ) {
            return redirect(ChangePassword::getUrl());
        }

        return $next($request);
    }
}
