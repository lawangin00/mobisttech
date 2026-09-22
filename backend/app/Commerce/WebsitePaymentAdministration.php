<?php

namespace App\Commerce;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Models\Admin;

/** Permissioned, read-only channel projection; merchant values and credentials never leave the backend. */
final class WebsitePaymentAdministration
{
    public function __construct(private PaymentProviders $providers) {}

    public function overview(IdentityAccount $actor): array
    {
        abort_unless($actor instanceof Admin && app(Access::class)->allows($actor, 'website.payments.manage'), 403);

        return array_map(function (array $channel): array {
            $code = $channel['code'];
            $configuration = config('commerce.providers.'.$code, []);
            $configuration = is_array($configuration) ? $configuration : [];

            return [
                'code' => $code,
                'label' => $channel['label'],
                'enabled' => (bool) ($configuration['enabled'] ?? false),
                'merchant_configured' => trim((string) ($configuration['merchant'] ?? '')) !== '',
                'available' => $channel['available'],
            ];
        }, $this->providers->checkoutChannels());
    }
}
