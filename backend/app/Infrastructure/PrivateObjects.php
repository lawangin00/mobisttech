<?php

namespace App\Infrastructure;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

final class PrivateObjects
{
    /** Internal storage primitive, never an authorization boundary or public upload endpoint. */
    public function put(string $key, string $contents, string $sha256): void
    {
        $this->validateKey($key);
        if (strlen($contents) > 25 * 1024 * 1024 || ! hash_equals(hash('sha256', $contents), $sha256)) {
            throw new InvalidArgumentException('Object size or digest validation failed.');
        }
        $disk = $this->disk($key);
        if ($disk->exists($key)) {
            throw new LogicException('Private object keys are immutable; allocate a new object identifier.');
        }
        if (! $disk->put($key, $contents, ['visibility' => 'private'])) {
            throw new RuntimeException('Private object write failed.');
        }
        if (! hash_equals($sha256, hash('sha256', $disk->get($key)))) {
            throw new RuntimeException('Private object verification failed.');
        }
    }

    public function get(string $key): string
    {
        $this->validateKey($key);

        return $this->disk($key)->get($key);
    }

    public function exists(string $key): bool
    {
        $this->validateKey($key);

        return $this->disk($key)->exists($key);
    }

    public function delete(string $key): void
    {
        $this->validateKey($key);
        $disk = $this->disk($key);
        if ($disk->exists($key) && (! $disk->delete($key) || $disk->exists($key))) {
            throw new RuntimeException('Private object deletion failed.');
        }
    }

    private function validateKey(string $key): void
    {
        // New immutable object names only; no inherited source paths, URLs, traversal or executable suffixes.
        if (! preg_match('/\A(?:acquisitions|backups|media|client-files)\/[0-9a-f-]{36}\.(?:json|pdf|png|jpg|bin)\z/', $key)) {
            throw new InvalidArgumentException('Invalid private object key.');
        }
    }

    private function disk(string $key): Filesystem
    {
        $name = config('infrastructure.private_disk');
        if (! in_array($name, ['local', 's3'], true)) {
            throw new LogicException('Private objects require a private local or S3 disk.');
        }
        if (app()->environment(['local', 'testing']) && $name !== 'local') {
            throw new LogicException('External object storage is disabled in isolated environments.');
        }

        if ($name === 'local') {
            $disk = Storage::disk('local');
            $path = rtrim($disk->path(''), '/\\');
            foreach (['', ...explode('/', $key)] as $segment) {
                if ($segment !== '') {
                    $path .= '/'.$segment;
                }
                if (is_link($path)) {
                    throw new LogicException('Private storage must not follow symbolic links.');
                }
            }
        }

        return Storage::disk($name);
    }
}
