<?php

namespace App\Http\Middleware;

use App\Identity\RealmSessionPolicy;
use Closure;
use Illuminate\Http\Request;

final class RecentlyAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->attributes->get('identity_realm') === 'admin'
            && app(RealmSessionPolicy::class)->recentlyAuthenticated($request), 403, 'Recent authentication is required.');

        return $next($request);
    }
}
