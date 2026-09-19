<?php

declare(strict_types=1);

// CI-only preflight: do not reveal the per-run signing key in logs.
if (getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, "Engagement signing preflight requires APP_ENV=testing.\n");
    exit(2);
}

chdir(__DIR__.'/../../backend');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$secret = config('engagement.unsubscribe_secret');
if (config('app.env') !== 'testing' || ! is_string($secret) || trim($secret) === '') {
    fwrite(STDERR, "CI engagement signing configuration is missing.\n");
    exit(1);
}

echo "CI_ENGAGEMENT_SIGNING=PASS\n";
