<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Symfony\Component\HttpFoundation\Cookie;

class IdentityCsrf extends PreventRequestForgery
{
    protected function runningUnitTests()
    {
        return false; // Security tests exercise the same CSRF gate as real requests.
    }

    protected function hasValidOrigin($request)
    {
        return false; // A Fetch Metadata header does not replace the scoped CSRF token.
    }

    protected function newCookie($request, $config)
    {
        $realm = $request->attributes->get('identity_realm');

        return new Cookie(
            $realm === 'customer' ? 'XSRF-TOKEN' : 'XSRF-TOKEN-'.$realm,
            $request->session()->token(), $this->availableAt(60 * $config['lifetime']),
            $config['path'], null, $config['secure'], false, false, 'lax',
        );
    }
}
