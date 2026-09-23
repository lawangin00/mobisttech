<?php

namespace Tests\Feature;

use App\Commerce\PaymentProviders;
use App\Commerce\WebsitePaymentAdministration;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/** W04: Admin status visibility must not leak merchant configuration or grant credential access. */
final class W04AdminPaymentOverviewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_payment_settings_permission_can_read_the_bounded_channel_status(): void
    {
        $service = new WebsitePaymentAdministration(new PaymentProviders);

        foreach ([[], ['website.payment-credentials.manage'], ['website.orders.manage']] as $permissions) {
            try {
                $service->overview($this->admin($permissions));
                $this->fail('An unrelated permission granted payment settings visibility.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertCount(4, $service->overview($this->admin(['website.payments.manage'])));
    }

    public function test_status_is_ordered_default_off_and_never_returns_merchant_values_or_secrets(): void
    {
        config()->set('commerce.providers.jazzcash', [
            'enabled' => true,
            'merchant' => 'synthetic-private-merchant-marker',
            'mode' => 'sandbox',
            'credentials' => 'synthetic-private-credential-marker',
        ]);
        $service = new WebsitePaymentAdministration(new PaymentProviders);
        $status = $service->overview($this->admin(['website.payments.manage']));

        $this->assertSame(['cod', 'jazzcash', 'easypaisa', 'card'], array_column($status, 'code'));
        $this->assertSame([true, false, false, false], array_column($status, 'available'));
        $this->assertTrue($status[1]['enabled']);
        $this->assertTrue($status[1]['merchant_configured']);
        $this->assertFalse($status[1]['available']);
        $serialized = json_encode($status, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('synthetic-private-merchant-marker', $serialized);
        $this->assertStringNotContainsString('synthetic-private-credential-marker', $serialized);
        $this->assertSame(['code', 'label', 'enabled', 'merchant_configured', 'available'], array_keys($status[1]));
    }

    public function test_status_and_cod_effective_policy_reject_truthy_nonboolean_provider_flags(): void
    {
        $service = new WebsitePaymentAdministration(new PaymentProviders);
        $actor = $this->admin(['website.payments.manage']);
        config()->set('commerce.providers.cod.enabled', 'false');
        config()->set('commerce.providers.jazzcash.enabled', 'false');
        $status = $service->overview($actor);
        $this->assertSame([false, false, false, false], array_column($status, 'enabled'));
        $this->assertSame([false, false, false, false], array_column($status, 'available'));
        $this->assertFalse($service->codEnabled());
        $this->assertSame(0, DB::table('site_configuration_revisions')
            ->where('domain', 'website.payments.cod')->count());
        DB::table('site_configuration_revisions')->insert([
            'domain' => 'website.payments.cod', 'version' => 1,
            'state' => 'published', 'snapshot' => json_encode(['cod_enabled' => true], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertFalse($service->codEnabled(), 'Published COD cannot override a malformed deployment flag.');
        $this->assertFalse($service->overview($actor)[0]['available']);
        config()->set('commerce.providers.cod.enabled', true);
        $this->assertTrue($service->codEnabled());
        $this->assertTrue($service->overview($actor)[0]['available']);
    }

    public function test_published_newer_cod_policy_does_not_expose_an_older_draft_as_actionable(): void
    {
        $actor = $this->admin(['website.payments.manage', 'website.publish']);
        foreach ([[1, 'draft', false], [2, 'published', true]] as [$version, $state, $enabled]) {
            DB::table('site_configuration_revisions')->insert([
                'domain' => 'website.payments.cod', 'version' => $version, 'state' => $state,
                'snapshot' => json_encode(['cod_enabled' => $enabled], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $settings = (new WebsitePaymentAdministration(new PaymentProviders))->settings($actor);
        $this->assertSame(2, $settings['published_version']);
        $this->assertTrue($settings['cod_enabled']);
        $this->assertNull($settings['draft']);
        DB::table('site_configuration_revisions')->insert([
            'domain' => 'website.payments.cod', 'version' => 3, 'state' => 'draft',
            'snapshot' => json_encode(['cod_enabled' => false], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $newer = (new WebsitePaymentAdministration(new PaymentProviders))->settings($actor);
        $this->assertSame(3, $newer['draft']['version']);
        $this->assertFalse($newer['draft']['cod_enabled']);
        $this->assertTrue($newer['cod_enabled'], 'An unpublished draft must not change effective COD.');
    }

    public function test_corrupt_cod_draft_must_not_be_misrepresented_as_a_publishable_boolean(): void
    {
        $actor = $this->admin(['website.payments.manage', 'website.publish']);
        DB::table('site_configuration_revisions')->insert([
            'domain' => 'website.payments.cod', 'version' => 1, 'state' => 'draft',
            'snapshot' => json_encode(['cod_enabled' => 'false'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $settings = (new WebsitePaymentAdministration(new PaymentProviders))->settings($actor);
        $this->assertTrue($settings['cod_enabled']);
        $this->assertNull($settings['draft']);
        $this->assertTrue($settings['draft_invalid']);
        $service = new WebsitePaymentAdministration(new PaymentProviders);
        $replacement = $service->saveDraft($actor, ['cod_enabled' => false]);
        $this->assertSame(2, $replacement['version']);
        $ready = $service->settings($actor);
        $this->assertFalse($ready['draft_invalid']);
        $this->assertSame($replacement['id'], $ready['draft']['id']);
        $this->assertTrue($ready['cod_enabled']);
        $service->publish($actor, $replacement['id']);
        $this->assertFalse($service->settings($actor)['cod_enabled']);
    }

    public function test_malformed_published_cod_policy_stays_off_and_can_be_recovered_through_authorized_admin(): void
    {
        $actor = $this->admin(['website.payments.manage', 'website.publish']);
        DB::table('site_configuration_revisions')->insert([
            'domain' => 'website.payments.cod', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['cod_enabled' => 'true'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = new WebsitePaymentAdministration(new PaymentProviders);
        $this->assertFalse($service->codEnabled(), 'Malformed published COD must not open checkout.');
        $this->assertFalse($service->overview($actor)[0]['available']);
        $settings = $service->settings($actor);
        $this->assertTrue($settings['published_invalid']);
        $this->assertFalse($settings['cod_enabled']);
        $this->assertSame(1, $settings['published_version']);
        $replacement = $service->saveDraft($actor, ['cod_enabled' => true]);
        $this->assertSame(2, $replacement['version']);
        $this->assertFalse($service->codEnabled(), 'Unpublished recovery draft must not open checkout.');
        $service->publish($actor, $replacement['id']);
        $this->assertTrue($service->codEnabled());
        $this->assertFalse($service->settings($actor)['published_invalid']);
    }

    private function admin(array $permissions): Admin
    {
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'W04 Synthetic Admin',
            'email' => Str::uuid().'@example.invalid',
            'password' => 'SyntheticPass123!',
            'permissions' => $permissions,
            'auth_version' => 1,
        ])->save();

        return $admin;
    }
}
