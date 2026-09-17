<?php

use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CustomerApiController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PosShellController;
use App\Http\Controllers\TeamMemberController;
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
        ['POST', '/auth/activity', 'activity', true],
        ['POST', '/auth/confirm-password', 'confirmPassword', true],
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
Route::get('/internal/admin/pos/login', [PosShellController::class, 'login'])
    ->defaults('identity_realm', 'admin')->middleware('identity')->name('admin.pos.login');

Route::prefix('/api/v1')->middleware(['identity', 'identity.auth', 'throttle:api-customer'])->group(function () {
    Route::post('/cart/quote', [CustomerApiController::class, 'cartQuote'])->defaults('identity_realm', 'customer')->name('api.customer.cart.quote');
    Route::post('/orders', [CustomerApiController::class, 'checkout'])->defaults('identity_realm', 'customer')->name('api.customer.orders.store');
    Route::get('/orders', [CustomerApiController::class, 'orders'])->defaults('identity_realm', 'customer')->name('api.customer.orders.index');
    Route::get('/orders/{order}', [CustomerApiController::class, 'order'])->defaults('identity_realm', 'customer')->name('api.customer.orders.show');
    Route::post('/orders/{order}/cancel', [CustomerApiController::class, 'cancel'])->defaults('identity_realm', 'customer')->name('api.customer.orders.cancel');
    Route::post('/orders/{order}/payments/retry', [CustomerApiController::class, 'retryPayment'])->defaults('identity_realm', 'customer')->name('api.customer.payments.retry');
    Route::post('/payments/{payment}/initiate', [CustomerApiController::class, 'initiatePayment'])->defaults('identity_realm', 'customer')->name('api.customer.payments.initiate');
    Route::post('/project-milestones/pay', [CustomerApiController::class, 'milestone'])->defaults('identity_realm', 'customer')->name('api.customer.milestones.pay');
    Route::get('/projects/{project}', [CustomerApiController::class, 'project'])->defaults('identity_realm', 'customer')->name('api.customer.projects.show');
    Route::get('/projects/{project}/files/{file}', [CustomerApiController::class, 'projectFile'])->defaults('identity_realm', 'customer')->name('api.customer.projects.files');
    Route::get('/wishlist', [CustomerApiController::class, 'wishlist'])->defaults('identity_realm', 'customer')->name('api.customer.wishlist.index');
    Route::post('/wishlist/{product}', [CustomerApiController::class, 'saveWishlist'])->defaults('identity_realm', 'customer')->name('api.customer.wishlist.store');
    Route::delete('/wishlist/{product}', [CustomerApiController::class, 'removeWishlist'])->defaults('identity_realm', 'customer')->name('api.customer.wishlist.destroy');
    Route::get('/notification-preferences', [CustomerApiController::class, 'notificationPreferences'])->defaults('identity_realm', 'customer')->name('api.customer.notifications.preferences');
    Route::put('/notification-preferences', [CustomerApiController::class, 'updateNotificationPreferences'])->defaults('identity_realm', 'customer')->name('api.customer.notifications.preferences.update');
    Route::get('/product-subscriptions', [CustomerApiController::class, 'subscriptions'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.index');
    Route::post('/product-subscriptions/{product}', [CustomerApiController::class, 'subscribe'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.store');
    Route::delete('/product-subscriptions/{product}', [CustomerApiController::class, 'unsubscribe'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.destroy');
    Route::get('/reviews', [CustomerApiController::class, 'reviews'])->defaults('identity_realm', 'customer')->name('api.customer.reviews.index');
    Route::post('/reviews', [CustomerApiController::class, 'submitReview'])->defaults('identity_realm', 'customer')->name('api.customer.reviews.store');
});
Route::prefix('/internal/admin')->middleware(['identity', 'identity.auth'])->group(function () {
    Route::get('/pos', [PosShellController::class, 'home'])->defaults('identity_realm', 'admin')->name('admin.pos.home');
    Route::get('/pos/workspace/{area}', [PosShellController::class, 'workspace'])->whereIn('area', ['sales', 'inventory', 'invoices', 'warranty', 'claims', 'reports'])
        ->defaults('identity_realm', 'admin')->name('admin.pos.workspace');
    Route::get('/team-members', [TeamMemberController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.team-members.index');
    Route::get('/roles', [TeamMemberController::class, 'roles'])->defaults('identity_realm', 'admin')->name('admin.roles.index');
    Route::post('/team-members', [TeamMemberController::class, 'store'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.team-members.store');
    Route::patch('/team-members/{member:public_id}', [TeamMemberController::class, 'update'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.team-members.update');
    Route::post('/roles', [TeamMemberController::class, 'storeRole'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.roles.store');
    Route::patch('/roles/{role:public_id}', [TeamMemberController::class, 'updateRole'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.roles.update');
    Route::delete('/roles/{role:public_id}', [TeamMemberController::class, 'destroyRole'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.roles.destroy');
    Route::get('/settings/integrations', [IntegrationController::class, 'page'])->defaults('identity_realm', 'admin')->name('admin.integrations.page');
    Route::get('/integrations', [IntegrationController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.integrations.index');
    Route::post('/integrations/{provider}/connect', [IntegrationController::class, 'connect'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.connect');
    Route::get('/integrations/{provider}/callback', [IntegrationController::class, 'callback'])->defaults('identity_realm', 'admin')->name('admin.integrations.callback');
    Route::post('/integrations/{provider}/test', [IntegrationController::class, 'test'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.test');
    Route::post('/integrations/google_drive/backup-now', [IntegrationController::class, 'backupNow'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.backup-now');
    Route::put('/integrations/google_drive/backup-settings', [IntegrationController::class, 'backupSettings'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.backup-settings');
    Route::delete('/integrations/{provider}', [IntegrationController::class, 'disconnect'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.disconnect');
    Route::put('/business-profile', [BusinessProfileController::class, 'update'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.business-profile.update');
});
Route::get('/sanctum/csrf-cookie', [IdentityController::class, 'csrf'])
    ->defaults('identity_realm', 'customer')->middleware('identity')->name('identity.csrf');
