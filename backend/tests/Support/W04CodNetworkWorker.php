<?php

// One bounded real HTTP request against an independently owned test-only server.
$job = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$base = $argv[2] ?? '';
if (getenv('APP_ENV') !== 'testing' || ! in_array($base, ['http://127.0.0.1:18171', 'http://127.0.0.1:18172'], true)) {
    fwrite(STDERR, "W04 network worker requires an isolated localhost test server.\n");
    exit(10);
}
$cookies = [];
foreach ($job['cookies'] as $name => $value) {
    $cookies[] = $name.'='.rawurlencode($value);
}
$curl = curl_init($base.$job['uri']);
curl_setopt_array($curl, [
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($job['body'], JSON_THROW_ON_ERROR),
    CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json',
        'X-CSRF-TOKEN: '.$job['csrf'], 'Cookie: '.implode('; ', $cookies)],
]);
file_put_contents($job['marker'], 'READY');
$response = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$error = curl_error($curl);
curl_close($curl);
if ($response === false) {
    fwrite(STDERR, $error);
    exit(11);
}
$payload = json_decode($response, true);
echo json_encode(['status' => $status, 'data' => $payload['data'] ?? null], JSON_THROW_ON_ERROR);
