<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('foundation', [
    'application' => 'mobiST Tech',
    'scope' => 'Application foundation only',
]))->name('foundation');
