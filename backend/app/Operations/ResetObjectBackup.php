<?php

namespace App\Operations;

use App\Infrastructure\PrivateObjects;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use RuntimeException;

final class ResetObjectBackup
{
    public function __construct(private PrivateObjects $objects) {}

    public function create(array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, 'is_string')));
        sort($keys, SORT_STRING);
        $items = [];
        foreach ($keys as $sourceKey) {
            $bytes = $this->objects->get($sourceKey);
            $sha = hash('sha256', $bytes);
            $encrypted = Crypt::encryptString(base64_encode($bytes));
            $backupKey = 'backups/'.Str::uuid().'.bin';
            $this->objects->put($backupKey, $encrypted, hash('sha256', $encrypted));
            $this->verifyOne($backupKey, $sha, strlen($bytes));
            $items[] = [
                'source_key' => $sourceKey,
                'backup_key' => $backupKey,
                'byte_size' => strlen($bytes),
                'sha256' => $sha,
            ];
        }

        return [
            'contract' => 'mobisttech-reset-object-backup.v1',
            'count' => count($items),
            'items' => $items,
            'manifest_sha256' => $this->itemsSha($items),
        ];
    }

    public function verify(array $manifest): void
    {
        if (($manifest['contract'] ?? null) !== 'mobisttech-reset-object-backup.v1' || ! is_array($manifest['items'] ?? null)) {
            throw new RuntimeException('RESET_OBJECT_BACKUP_MANIFEST_INVALID');
        }
        foreach ($manifest['items'] as $item) {
            $this->verifyOne((string) $item['backup_key'], (string) $item['sha256'], (int) $item['byte_size']);
        }
        $actual = $this->itemsSha($manifest['items']);
        if (! hash_equals((string) ($manifest['manifest_sha256'] ?? ''), $actual)) {
            throw new RuntimeException('RESET_OBJECT_BACKUP_HASH_MISMATCH');
        }
    }

    public function discardBackups(array $manifest): void
    {
        foreach ($manifest['items'] ?? [] as $item) {
            $key = $item['backup_key'] ?? null;
            if (is_string($key) && $this->objects->exists($key)) {
                $this->objects->delete($key);
            }
        }
    }

    public function cleanupSources(array $manifest): void
    {
        $this->verify($manifest);
        foreach ($manifest['items'] as $item) {
            $this->objects->delete((string) $item['source_key']);
        }
    }

    private function itemsSha(array $items): string
    {
        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new RuntimeException('RESET_OBJECT_BACKUP_MANIFEST_INVALID');
            }
            ksort($item, SORT_STRING);
            $normalized[] = $item;
        }

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function verifyOne(string $backupKey, string $expectedSha, int $expectedSize): void
    {
        $encrypted = $this->objects->get($backupKey);
        $decoded = base64_decode(Crypt::decryptString($encrypted), true);
        if (! is_string($decoded) || strlen($decoded) !== $expectedSize || ! hash_equals($expectedSha, hash('sha256', $decoded))) {
            throw new RuntimeException('RESET_OBJECT_BACKUP_VERIFICATION_FAILED');
        }
    }
}
