<?php

namespace App\Infrastructure;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class VersionedCache
{
    /** Only sanitized public projections may use this cache; never stock locks or authorization. */
    public function remember(string $domain, string $resource, Closure $readMaster): mixed
    {
        if (! preg_match('/\A[a-z][a-z0-9_.-]{0,79}\z/', $domain) || strlen($resource) > 512) {
            throw new InvalidArgumentException('Invalid derived cache namespace.');
        }

        // Always consult MySQL; a database failure must not expose a stale cached result.
        $version = DB::table('publication_versions')->where('domain', $domain)->value('version');
        if ($version === null) {
            return $readMaster();
        }
        $key = 'publication:'.$domain.':'.$version.':'.hash('sha256', $resource);
        $cache = Cache::store(config('infrastructure.derived_cache_store'));
        try {
            $hit = $cache->get($key);
            if (is_array($hit) && array_key_exists('value', $hit)) {
                return $hit['value'];
            }
        } catch (Throwable) {
            Log::warning('Derived cache unavailable; reading master.', ['domain' => $domain]);

            return $readMaster();
        }

        // Run the master callback outside the cache exception handler; never retry application errors.
        $value = $readMaster();
        try {
            $cache->put($key, ['value' => $value], config('infrastructure.derived_cache_ttl'));
        } catch (Throwable) {
            Log::warning('Derived cache write unavailable.', ['domain' => $domain]);
        }

        return $value;
    }
}
