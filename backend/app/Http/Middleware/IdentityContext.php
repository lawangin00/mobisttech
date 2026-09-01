<?php

namespace App\Http\Middleware;

use App\Identity\RealmSessionPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IdentityContext
{
    public function handle(Request $request, Closure $next)
    {
        $realm = $request->route()->defaults['identity_realm'];
        abort_unless(in_array($realm, ['customer', 'admin'], true), 404);
        $request->attributes->set('identity_realm', $realm);
        app(RealmSessionPolicy::class)->configureRequest($realm);
        if (! $request->isMethodSafe() && $request->isJson()) {
            try {
                json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                abort(400, 'Malformed JSON.');
            }
        }
        $origin = config('identity.'.($realm === 'customer' ? 'customer' : 'admin').'_origin');
        if (! app()->environment(['local', 'testing'])) {
            abort_unless(str_starts_with($origin, 'https://') && $request->getHost() === parse_url($origin, PHP_URL_HOST), 403);
            abort_if(parse_url(config('identity.customer_origin'), PHP_URL_HOST) === parse_url(config('identity.admin_origin'), PHP_URL_HOST), 503);
        }
        if (! $request->isMethodSafe() && $request->headers->has('Origin')) {
            abort_unless($request->header('Origin') === $origin, 403);
        }
        foreach (app('cookie')->getQueuedCookies() as $cookie) {
            app('cookie')->unqueue($cookie->getName(), $cookie->getPath());
        }
        config(['session.cookie' => 'mobist_'.$realm.'_session', 'session.path' => $realm === 'customer' ? '/' : '/internal/'.$realm,
            'session.domain' => null, 'session.http_only' => true, 'session.same_site' => 'lax',
            'session.secure' => ! app()->environment(['local', 'testing'])]);
        app('cookie')->setDefaultPathAndDomain(config('session.path'), null, config('session.secure'), 'lax');
        // Request-local state also prevents long-lived/test workers from reusing another realm's guard/store.
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        Auth::forgetGuards();
        Auth::shouldUse($realm);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
