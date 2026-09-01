<?php

use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\IntegrationController;
use Illuminate\Support\Facades\Route;

foreach (['customer', 'admin'] as $realm) {
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
Route::prefix('/internal/admin')->middleware(['identity', 'identity.auth'])->group(function () {
    Route::get('/settings/integrations', [IntegrationController::class, 'page'])->defaults('identity_realm', 'admin')->name('admin.integrations.page');
    Route::get('/integrations', [IntegrationController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.integrations.index');
    Route::post('/integrations/{provider}/connect', [IntegrationController::class, 'connect'])->defaults('identity_realm', 'admin')->middleware('throttle:identity')->name('admin.integrations.connect');
    Route::get('/integrations/{provider}/callback', [IntegrationController::class, 'callback'])->defaults('identity_realm', 'admin')->name('admin.integrations.callback');
    Route::post('/integrations/{provider}/test', [IntegrationController::class, 'test'])->defaults('identity_realm', 'admin')->middleware('throttle:identity')->name('admin.integrations.test');
    Route::post('/integrations/google_drive/backup-now', [IntegrationController::class, 'backupNow'])->defaults('identity_realm', 'admin')->middleware('throttle:identity')->name('admin.integrations.backup-now');
    Route::put('/integrations/google_drive/backup-settings', [IntegrationController::class, 'backupSettings'])->defaults('identity_realm', 'admin')->middleware('throttle:identity')->name('admin.integrations.backup-settings');
    Route::delete('/integrations/{provider}', [IntegrationController::class, 'disconnect'])->defaults('identity_realm', 'admin')->middleware('throttle:identity')->name('admin.integrations.disconnect');
    Route::put('/business-profile', [BusinessProfileController::class, 'update'])->defaults('identity_realm', 'admin')->middleware('throttle:identity')->name('admin.business-profile.update');
});
Route::get('/sanctum/csrf-cookie', [IdentityController::class, 'csrf'])
    ->defaults('identity_realm', 'customer')->middleware('identity')->name('identity.csrf');
