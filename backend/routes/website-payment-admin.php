<?php

use App\Http\Controllers\WebsitePaymentAdministrationController;
use Illuminate\Support\Facades\Route;

Route::get('/internal/admin/website/payment-channels', [WebsitePaymentAdministrationController::class, 'channels'])
    ->defaults('identity_realm', 'admin')
    ->middleware(['identity', 'identity.auth', 'throttle:identity'])
    ->name('admin.website.payment-channels.index');
