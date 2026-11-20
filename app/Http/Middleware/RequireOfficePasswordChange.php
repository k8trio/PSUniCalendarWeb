<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOfficePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user?->role === 'office'
            && $user->must_change_password
            && ! $request->routeIs('office.password.force', 'office.password.force.update')
        ) {
            return redirect()->route('office.password.force');
        }

        return $next($request);
    }
}
