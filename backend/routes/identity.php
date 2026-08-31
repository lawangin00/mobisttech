<?php

use App\Http\Controllers\IdentityController;
use Illuminate\Support\Facades\Route;

foreach (['customer', 'admin', 'superadmin', 'website_admin'] as $realm) {
    $prefix = $realm === 'customer' ? '/api/v1' : '/internal/'.$realm;
    $routes = [
        ['GET', '/auth/csrf-cookie', 'csrf', false],
        ['POST', '/auth/login', 'login', false],
        ['POST', '/auth/logout', 'logout', true],
        ['POST', '/auth/forgot-password', 'forgot', false],
        ['POST', '/auth/reset-password', 'reset', false],
        ['PATCH', '/auth/password', 'changePassword', true],
        ['GET', '/account', 'account', true],
    ];
    if ($realm === 'customer') {
        $routes[] = ['POST', '/auth/register', 'register', false];
    }
    if ($realm === 'admin') {
        $routes[] = ['GET', '/outlets', 'outlets', true];
        $routes[] = ['POST', '/outlets/select', 'selectOutlet', true];
    }
    foreach ($routes as [$method, $path, $action, $authenticated]) {
        $route = Route::match([$method], $prefix.$path, [IdentityController::class, $action])
            ->defaults('identity_realm', $realm)->middleware('identity')->name('identity.'.$realm.'.'.$action);
        if ($authenticated) {
            $route->middleware('identity.auth');
        }
        if ($method !== 'GET') {
            $route->middleware('throttle:identity');
        }
    }
}
Route::get('/sanctum/csrf-cookie', [IdentityController::class, 'csrf'])
    ->defaults('identity_realm', 'customer')->middleware('identity')->name('identity.csrf');
