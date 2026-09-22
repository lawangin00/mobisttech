<?php

use App\Http\Controllers\WebsitePaymentAdministrationController;
use Illuminate\Support\Facades\Route;

Route::get('/internal/admin/website/payment-settings', [WebsitePaymentAdministrationController::class, 'page'])
    ->defaults('identity_realm', 'admin')
    ->middleware(['identity', 'identity.auth', 'throttle:identity'])
    ->name('admin.website.payment-settings.page');

Route::get('/internal/admin/website/payment-channels', [WebsitePaymentAdministrationController::class, 'channels'])
    ->defaults('identity_realm', 'admin')
    ->middleware(['identity', 'identity.auth', 'throttle:identity'])
    ->name('admin.website.payment-channels.index');

// The status endpoints above remain strictly read-only. These distinct routes only
// manage the nonsecret COD flag; no external merchant or credential activation.
Route::post('/internal/admin/website/payment-settings/drafts', [WebsitePaymentAdministrationController::class, 'draft'])
    ->defaults('identity_realm', 'admin')
    ->middleware(['identity', 'identity.auth', 'throttle:identity'])
    ->name('admin.website.payment-settings.draft');

Route::post('/internal/admin/website/payment-settings/drafts/{revision}/publish', [WebsitePaymentAdministrationController::class, 'publish'])
    ->whereNumber('revision')->defaults('identity_realm', 'admin')
    ->middleware(['identity', 'identity.auth', 'throttle:identity'])
    ->name('admin.website.payment-settings.publish');
