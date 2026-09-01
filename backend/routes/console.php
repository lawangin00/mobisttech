<?php

use App\Backups\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('foundation:check', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Foundation checks are limited to isolated local/test targets.');

        return 1;
    }

    DB::selectOne('SELECT 1 AS ready');
    $key = 'foundation-check-'.Str::uuid();
    $path = 'foundation-checks/'.$key.'.txt';

    try {
        Cache::put($key, 'synthetic', 30);
        Storage::disk('local')->put($path, 'synthetic');
        if (Cache::get($key) !== 'synthetic' || Storage::disk('local')->get($path) !== 'synthetic') {
            throw new RuntimeException('Foundation cache/storage probe failed.');
        }
        $this->info('PASS: isolated MySQL, cache and private local storage.');
    } finally {
        Cache::forget($key);
        Storage::disk('local')->delete($path);
    }

    return 0;
})->purpose('Verify target-only foundation connectivity without business data');

Schedule::call(fn () => app(BackupService::class)->dispatchDue())
    ->name('google-drive-business-backup')
    ->hourly()
    ->withoutOverlapping();
