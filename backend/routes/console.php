<?php

use App\Backups\BackupService;
use App\Identity\FirstAdminProvisioning;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('setup:first-admin', function (FirstAdminProvisioning $provisioner) {
    // Never accept an owner password through argv, committed seeds or environment defaults.
    if (! $this->input->isInteractive()) {
        $this->error('First Admin setup requires an interactive private terminal.');

        return 1;
    }
    $name = (string) $this->ask('Initial Admin name');
    $email = (string) $this->ask('Initial Admin email');
    $password = (string) $this->secret('New Admin password (12+ characters, mixed case, number, symbol)');
    $confirmation = (string) $this->secret('Confirm new Admin password');
    if ($password === '' || ! hash_equals($password, $confirmation)) {
        $this->error('Password confirmation failed; no account created.');

        return 1;
    }
    try {
        $provisioner->create($name, $email, $password);
    } catch (Throwable $error) {
        $this->error('First Admin setup refused. Check the fresh-database prerequisite and input.');

        return 1;
    }
    $this->info('Initial protected Admin created. Sign in and create the first outlet.');

    return 0;
})->purpose('Interactively create one protected Admin on an empty fresh target only');

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
