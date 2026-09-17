<?php

namespace App\Operations;

final class AuditRedactor
{
    private const SECRET_KEY = '/(?:password|passwd|secret|token|authorization|cookie|credential|cipher|cnic|national[_-]?id|app[_-]?key|client[_-]?secret|refresh[_-]?token)/i';

    public function payload(array $payload): array
    {
        return $this->walk($payload);
    }

    public function path(string $value): string
    {
        $path = parse_url($value, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        $path = preg_replace('/[\x00-\x1F\x7F]/', '', $path) ?: '/';

        return mb_substr($path, 0, 500);
    }

    private function walk(array $value): array
    {
        foreach ($value as $key => $item) {
            if (preg_match(self::SECRET_KEY, (string) $key)) {
                $value[$key] = '[REDACTED]';

                continue;
            }
            if (is_array($item)) {
                $value[$key] = $this->walk($item);
            }
        }

        return $value;
    }
}
