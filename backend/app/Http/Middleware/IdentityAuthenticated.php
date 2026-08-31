<?php

namespace App\Http\Middleware;

use App\Identity\PosSessions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdentityAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        $realm = $request->attributes->get('identity_realm');
        $guard = Auth::guard($realm);
        $user = $guard->user();
        abort_unless($user && $user->usable(), 401);
        if ($guard->viaRemember()) {
            if (in_array($realm, ['admin', 'superadmin'], true)) {
                abort_if(app(PosSessions::class)->register($request, $realm, $user->id), 401);
            }
            $request->session()->put('identity_version', $user->auth_version);
        }
        if ((int) $request->session()->get('identity_version') !== $user->auth_version) {
            $guard->logout();
            $request->session()->invalidate();
            abort(401);
        }
        if (in_array($realm, ['admin', 'superadmin'], true)) {
            abort_if(app(PosSessions::class)->validateCurrent($request, $realm, $user->id), 401);
        }

        return $next($request);
    }
}
