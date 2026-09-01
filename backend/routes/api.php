<?php

use App\Http\Controllers\BusinessProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json([
    'data' => ['service' => 'mobisttech-backend', 'status' => 'ok', 'contract' => 'v1'],
])->header('Cache-Control', 'no-store'));
Route::get('/v1/business-profile', [BusinessProfileController::class, 'show'])->name('business-profile.show');
