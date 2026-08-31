<?php

namespace App\Addendum;

use Illuminate\Support\Str;
use InvalidArgumentException;

/** Versioned immutable inputs for future sale/payment engines, not a promotion or payment processor. */
final class MoneySnapshot
{
    public static function amount(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/\A(?:0|[1-9][0-9]{0,16})\.[0-9]{2}\z/', $value) || bccomp($value, '0.00', 2) <= 0) {
            throw new InvalidArgumentException('A positive canonical decimal(19,2) string is required.');
        }

        return $value;
    }

    public static function adjustment(string $id, string $kind, mixed $amount, string $reason, ?string $source = null): array
    {
        if (! Str::isUuid($id) || ! in_array($kind, ['manual_discount', 'promotion', 'loyalty_redemption', 'trade_in_credit'], true)
            || trim($reason) === '' || mb_strlen($reason) > 500 || ($source !== null && ! Str::isUuid($source)) || ($kind !== 'manual_discount' && $source === null)) {
            throw new InvalidArgumentException('Invalid adjustment identity, source or reason.');
        }

        return ['contract' => 'monetary-adjustment.v1', 'id' => $id, 'kind' => $kind, 'treatment' => $kind === 'trade_in_credit' ? 'tender' : 'discount',
            'amount' => self::amount($amount), 'currency' => 'PKR', 'source_reference' => $source, 'reason' => $reason];
    }

    public static function milestone(string $id, string $quote, int $sequence, mixed $amount, string $scopeHash): array
    {
        if (! Str::isUuid($id) || ! Str::isUuid($quote) || $sequence < 1 || $sequence > 4294967295 || ! preg_match('/\A[0-9a-f]{64}\z/', $scopeHash)) {
            throw new InvalidArgumentException('Invalid approved milestone identity or scope digest.');
        }

        return ['contract' => 'project-milestone.v1', 'id' => $id, 'quote_id' => $quote, 'sequence' => $sequence,
            'approved_amount' => self::amount($amount), 'currency' => 'PKR', 'approved_scope_sha256' => $scopeHash];
    }

    public static function digest(array $snapshot): string
    {
        // These DTOs are flat. Stable key ordering avoids replay drift between serializers.
        ksort($snapshot);

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
