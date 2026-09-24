<?php

namespace Tests\Feature;

use App\Commerce\PaymentProviders;
use App\Commerce\WebsitePaymentPresentation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class W04PaymentPresentationValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_nonsecret_channel_policy_is_strictly_allowlisted_and_does_not_contain_activation_fields(): void
    {
        $policy = new WebsitePaymentPresentation;
        $default = $policy->defaults();
        $this->assertSame(['channels', 'cod_min_amount', 'cod_max_amount'], array_keys($default));
        $this->assertSame(['cod', 'jazzcash', 'easypaisa', 'card'], array_keys($default['channels']));
        $candidate = $default;
        $candidate['channels']['cod'] = ['label' => 'Pay at delivery', 'instructions' => 'Cash upon receipt.'];
        $candidate['cod_min_amount'] = '100.00';
        $candidate['cod_max_amount'] = '50000.00';
        $this->assertSame($candidate, $policy->validate($candidate));
        foreach ([
            $candidate + ['merchant' => 'not-allowed'],
            array_replace($candidate, ['cod_min_amount' => '50001.00']),
            array_replace($candidate, ['cod_max_amount' => '99.00']),
            array_replace($candidate, ['cod_min_amount' => '10']),
            array_replace($candidate, ['channels' => array_replace($candidate['channels'], ['card' => ['label' => 'Card', 'instructions' => '', 'enabled' => true]])]),
            array_replace($candidate, ['channels' => array_replace($candidate['channels'], ['cod' => ['label' => '<script>', 'instructions' => '']])]),
        ] as $invalid) {
            try {
                $policy->validate($invalid);
                $this->fail('Unapproved payment presentation fields were accepted.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }
    }

    public function test_published_presentation_changes_only_new_checkout_channel_copy_not_provider_activation(): void
    {
        $policy = new WebsitePaymentPresentation;
        $payload = $policy->defaults();
        $payload['channels']['cod'] = ['label' => 'Pay upon arrival', 'instructions' => 'Have cash ready.'];
        $payload['channels']['jazzcash'] = ['label' => 'JazzCash Wallet', 'instructions' => 'Use hosted checkout.'];
        $payload['cod_min_amount'] = '100.00';
        $payload['cod_max_amount'] = '500.00';
        $this->assertSame($payload, $policy->validate(json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR)));
        DB::table('site_configuration_revisions')->insert([
            'domain' => 'website.payments.presentation', 'version' => 1,
            'state' => 'published', 'snapshot' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame($payload, $policy->published());
        $channels = (new PaymentProviders)->checkoutChannels();
        $this->assertSame('Pay upon arrival', $channels[0]['label']);
        $this->assertSame('Have cash ready.', $channels[0]['instructions']);
        $this->assertSame('100.00', $channels[0]['cod_min_amount']);
        $this->assertSame('500.00', $channels[0]['cod_max_amount']);
        $this->assertArrayNotHasKey('cod_min_amount', $channels[1]);
        $this->assertArrayNotHasKey('cod_max_amount', $channels[1]);
        $this->assertSame('JazzCash Wallet', $channels[1]['label']);
        $this->assertFalse($channels[1]['available']);
        $this->assertFalse($channels[2]['available']);
        $this->assertFalse($channels[3]['available']);
    }
}
