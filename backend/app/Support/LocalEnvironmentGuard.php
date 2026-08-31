<?php

namespace App\Support;

use LogicException;

final class LocalEnvironmentGuard
{
    public static function validate(array $database, array $redis, bool $external): void
    {
        if (($database['driver'] ?? null) !== 'mysql'
            || ($database['host'] ?? null) !== '127.0.0.1'
            || (int) ($database['port'] ?? 0) !== 13306
            || ! in_array($database['database'] ?? null, ['mobisttech_local', 'mobisttech_test'], true)
            || ($database['username'] ?? null) !== 'mobisttech'
            || ! empty($database['url'])
            || ! empty($database['unix_socket'])) {
            throw new LogicException('Local execution requires the isolated mobiST Tech MySQL target.');
        }

        if (($redis['host'] ?? null) !== '127.0.0.1'
            || (int) ($redis['port'] ?? 0) !== 16379
            || ! empty($redis['url']) || $external) {
            throw new LogicException('Local integrations must remain isolated and disabled.');
        }
    }
}
