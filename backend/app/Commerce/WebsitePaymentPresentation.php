<?php

namespace App\Commerce;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Read-only, nonsecret payment-channel presentation policy; never an activation authority. */
final class WebsitePaymentPresentation
{
    private const DOMAIN = 'website.payments.presentation';

    private const DEFAULT_LABELS = [
        'cod' => 'Cash on Delivery',
        'jazzcash' => 'JazzCash',
        'easypaisa' => 'Easypaisa',
        'card' => 'Credit / Debit Card',
    ];

    public function defaults(): array
    {
        return [
            'channels' => array_map(fn (string $label): array => ['label' => $label, 'instructions' => ''], self::DEFAULT_LABELS),
            'cod_min_amount' => null,
            'cod_max_amount' => null,
        ];
    }

    /** Strict allowlisting prevents arbitrary HTML, provider fields, secret storage and mode changes. */
    public function validate(array $input): array
    {
        $defaults = $this->defaults();
        abort_unless(count($input) === count($defaults) && ! array_diff_key($input, $defaults), 422);
        abort_unless(is_array($input['channels']) && count($input['channels']) === count(self::DEFAULT_LABELS)
            && ! array_diff_key($input['channels'], self::DEFAULT_LABELS), 422);
        foreach ($input['channels'] as $code => $details) {
            abort_unless(is_array($details) && count($details) === 2
                && ! array_diff_key($details, ['label' => '', 'instructions' => '']), 422);
            foreach (['label' => 80, 'instructions' => 500] as $field => $max) {
                $value = $details[$field];
                abort_unless(is_string($value) && mb_strlen($value) <= $max
                    && ! preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/u', $value), 422);
                abort_unless($field !== 'label' || trim($value) !== '', 422);
            }
        }
        foreach (['cod_min_amount', 'cod_max_amount'] as $key) {
            $value = $input[$key];
            abort_unless($value === null || (is_string($value)
                && preg_match('/\A(?:0|[1-9][0-9]{0,8})\.[0-9]{2}\z/D', $value) === 1), 422);
        }
        if ($input['cod_min_amount'] !== null && $input['cod_max_amount'] !== null) {
            abort_unless(bccomp($input['cod_min_amount'], $input['cod_max_amount'], 2) <= 0, 422);
        }

        $normalized = $this->defaults();
        foreach (array_keys(self::DEFAULT_LABELS) as $code) {
            $normalized['channels'][$code] = [
                'label' => $input['channels'][$code]['label'],
                'instructions' => $input['channels'][$code]['instructions'],
            ];
        }
        $normalized['cod_min_amount'] = $input['cod_min_amount'];
        $normalized['cod_max_amount'] = $input['cod_max_amount'];

        return $normalized;
    }

    /** Applies to newly created COD orders only, after server-side discounts. */
    public function assertCodAmount(string $amount): void
    {
        $policy = $this->published();
        if ($policy['cod_min_amount'] !== null && bccomp($amount, $policy['cod_min_amount'], 2) < 0) {
            abort(422, 'Order amount is below the Cash on Delivery minimum.');
        }
        if ($policy['cod_max_amount'] !== null && bccomp($amount, $policy['cod_max_amount'], 2) > 0) {
            abort(422, 'Order amount exceeds the Cash on Delivery maximum.');
        }
    }

    public function published(): array
    {
        $snapshot = DB::table('site_configuration_revisions')->where('domain', self::DOMAIN)
            ->where('state', 'published')->orderByDesc('version')->value('snapshot');
        if (! is_string($snapshot)) {
            return $this->defaults();
        }
        try {
            $value = json_decode($snapshot, true, flags: JSON_THROW_ON_ERROR);

            return is_array($value) ? $this->validate($value) : $this->defaults();
        } catch (\JsonException|HttpException) {
            // Invalid or tampered settings cannot activate a channel or override defaults.
            return $this->defaults();
        }
    }
}
