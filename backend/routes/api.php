<?php

use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\WebsiteApiController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json([
    'data' => ['service' => 'mobisttech-backend', 'status' => 'ok', 'contract' => 'v1'],
])->header('Cache-Control', 'no-store'));

Route::prefix('/v1')->middleware('throttle:api-public')->group(function () {
    Route::get('/business-profile', [BusinessProfileController::class, 'show'])->name('business-profile.show');
    Route::get('/website-profile', [WebsiteApiController::class, 'profile'])->name('api.website-profile');
    Route::get('/catalogue/products', [WebsiteApiController::class, 'catalogue'])->name('api.catalogue.index');
    Route::get('/catalogue/products/{slug}', [WebsiteApiController::class, 'product'])->name('api.catalogue.show');
    Route::get('/catalogue/categories', [WebsiteApiController::class, 'categories'])->name('api.categories.index');
    Route::get('/content', [WebsiteApiController::class, 'contentIndex'])->name('api.content.index');
    Route::get('/content/pages/{slug}/media/{media}', [WebsiteApiController::class, 'pageMedia'])
        ->where(['slug' => '[a-z0-9-]+', 'media' => '[1-9][0-9]*'])->name('api.pages.media');
    Route::get('/content/pages/{slug}', [WebsiteApiController::class, 'page'])->where('slug', '[a-z0-9-]+')->name('api.pages.show');
    Route::get('/content/policies', [WebsiteApiController::class, 'policies'])->name('api.policies.index');
    Route::get('/software/{slug}', [WebsiteApiController::class, 'software'])->name('api.software.show');
    Route::get('/software/{slug}/media/{media}', [WebsiteApiController::class, 'softwareMedia'])->whereNumber('media')->name('api.software.media');
    Route::get('/software/{slug}/{section}', [WebsiteApiController::class, 'softwareSection'])
        ->where('section', 'privacy|terms|faq|releases')->name('api.software.section');
    Route::get('/services', [WebsiteApiController::class, 'services'])->name('api.services.index');
    Route::get('/consultation', [WebsiteApiController::class, 'consultation'])->name('api.consultation.show');
});

Route::prefix('/v1')->middleware('throttle:api-write')->group(function () {
    Route::post('/enquiries', [WebsiteApiController::class, 'enquiry'])->name('api.enquiries.store');
    Route::post('/guest-wishlist', [WebsiteApiController::class, 'guestWishlist'])->name('api.guest-wishlist.index');
    Route::post('/guest-wishlist/{product}', [WebsiteApiController::class, 'saveGuestWishlist'])->whereUuid('product')->name('api.guest-wishlist.store');
    Route::delete('/guest-wishlist/{product}', [WebsiteApiController::class, 'removeGuestWishlist'])->whereUuid('product')->name('api.guest-wishlist.destroy');
});

Route::post('/v1/payment-callbacks/{gateway}', [WebsiteApiController::class, 'paymentCallback'])
    ->middleware('throttle:api-callback')->where('gateway', 'jazzcash|easypaisa|card')->name('api.payments.callback');
