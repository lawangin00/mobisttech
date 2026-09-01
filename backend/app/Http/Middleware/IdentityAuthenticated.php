<?php

namespace App\Http\Middleware;

use App\Identity\PosSessions;
use App\Identity\RealmSessionPolicy;
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
        $policy = app(RealmSessionPolicy::class);
        $policy->configureGuard($guard, $realm);
        if ($guard->viaRemember()) {
            if ($realm === 'admin') {
                $guard->logout();
                $request->session()->invalidate();
                abort(401);
            }
            $request->session()->put('identity_version', $user->auth_version);
            $policy->login($request, $realm);
        }
        if ((int) $request->session()->get('identity_version') !== $user->auth_version) {
            $guard->logout();
            $request->session()->invalidate();
            abort(401);
        }
        if ($realm === 'admin') {
            abort_if(app(PosSessions::class)->validateCurrent($request, $realm, $user->id), 401);
        }

        if ($policy->assertActive($request, $realm, $user)) {
            if ($realm === 'admin') {
                app(PosSessions::class)->release($request, $realm, $user->id);
            }
            $guard->logout();
            $request->session()->invalidate();
            abort(401);
        }

        $response = $next($request);
        $policy->recordHumanActivity($request, $realm, $user->id);

        return $response;
    }
}
