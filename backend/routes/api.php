<?php

use Illuminate\Support\Facades\Route;

Route::get('/v1/health', fn () => response()->json([
    'data' => ['service' => 'mobisttech-backend', 'status' => 'ok', 'contract' => 'v1'],
])->header('Cache-Control', 'no-store'));
