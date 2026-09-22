<?php

// Dispatch one real Laravel HTTP-kernel request in an independent PHP process.
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (app()->environment() !== 'testing' || DB::connection()->getDatabaseName() !== 'mobisttech_test') {
    fwrite(STDERR, "W04 HTTP worker requires disposable test database.\n");
    exit(10);
}
$job = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$request = Request::create($job['uri'], 'POST', [], $job['cookies'], [], [
    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    'HTTP_X_CSRF_TOKEN' => $job['csrf'], 'REMOTE_ADDR' => $job['ip'],
], json_encode($job['body'], JSON_THROW_ON_ERROR));
file_put_contents($job['marker'], 'READY');
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
$payload = json_decode($response->getContent(), true);
echo json_encode(['status' => $response->getStatusCode(), 'data' => $payload['data'] ?? null], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
