<?php

namespace Tests\Feature;

use App\Commerce\PaymentProviders;
use App\Commerce\WebsitePaymentPresentation;
use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class W04PaymentPresentationAdministrationHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_separate_nonsecret_draft_publish_is_permissioned_atomic_and_never_activates_a_provider(): void
    {
        $draftPath = '/internal/admin/website/payment-settings/presentation/drafts';
        $policy = app(WebsitePaymentPresentation::class)->defaults();
        $policy['channels']['cod'] = ['label' => 'Pay on arrival', 'instructions' => 'Have cash ready.'];
        $policy['channels']['jazzcash'] = ['label' => 'JazzCash wallet', 'instructions' => 'Hosted only.'];
        $policy['cod_min_amount'] = '100.00';
        $policy['cod_max_amount'] = '500.00';
        $guest = $this->client();
        $this->send($guest, 'POST', $draftPath, $policy)->assertUnauthorized();

        [$unrelated, $outlet] = $this->admin(['shops.enter', 'website.payment-credentials.manage']);
        $denied = $this->client();
        $this->login($denied, $unrelated, $outlet);
        $this->send($denied, 'POST', $draftPath, $policy)->assertForbidden();

        [$editor, $editorOutlet] = $this->admin(['shops.enter', 'website.payments.manage']);
        $client = $this->client();
        $this->login($client, $editor, $editorOutlet);
        $invalid = $policy + ['merchant' => 'never-store'];
        $this->send($client, 'POST', $draftPath, $invalid)->assertStatus(422);
        $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', 'website.payments.presentation')->count());
        $this->assertSame($policy, app(WebsitePaymentPresentation::class)->validate($policy));
        $response = $this->send($client, 'POST', $draftPath, $policy);
        $saved = $response->assertCreated()->json('data');
        $this->assertSame($policy, $saved['policy']);
        $this->assertSame('draft', DB::table('site_configuration_revisions')->where('id', $saved['id'])->value('state'));
        $this->assertSame('Cash on Delivery', app(PaymentProviders::class)->checkoutChannels()[0]['label']);
        $this->send($client, 'POST', "$draftPath/{$saved['id']}/publish", [])->assertForbidden();

        [$publisher, $publisherOutlet] = $this->admin(['shops.enter', 'website.payments.manage', 'website.publish']);
        $publisherClient = $this->client();
        $this->login($publisherClient, $publisher, $publisherOutlet);
        $this->send($publisherClient, 'POST', "$draftPath/{$saved['id']}/publish", ['enabled' => true])->assertStatus(422);
        $this->send($publisherClient, 'POST', "$draftPath/{$saved['id']}/publish", [])->assertOk();
        $this->assertSame('Pay on arrival', app(PaymentProviders::class)->checkoutChannels()[0]['label']);
        $this->assertSame('Have cash ready.', app(PaymentProviders::class)->checkoutChannels()[0]['instructions']);
        $this->assertFalse(app(PaymentProviders::class)->checkoutChannels()[1]['available']);
        $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', 'website.payments.cod')->count());
        $this->send($publisherClient, 'POST', "$draftPath/{$saved['id']}/publish", [])->assertStatus(409);
        $this->assertSame(1, DB::table('site_configuration_revisions')->where('domain', 'website.payments.presentation')->where('state', 'published')->count());
    }

    public function test_stale_foreign_and_malformed_presentation_drafts_reject_without_changing_effective_policy(): void
    {
        [$publisher, $outlet] = $this->admin(['shops.enter', 'website.payments.manage', 'website.publish']);
        $client = $this->client();
        $this->login($client, $publisher, $outlet);
        $path = '/internal/admin/website/payment-settings/presentation/drafts';
        $foreign = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.payments.cod', 'version' => 1, 'state' => 'draft',
            'snapshot' => json_encode(['cod_enabled' => false], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->send($client, 'POST', "$path/$foreign/publish", [])->assertStatus(409);
        $policy = app(WebsitePaymentPresentation::class)->defaults();
        $first = $this->send($client, 'POST', $path, $policy)->assertCreated()->json('data');
        $second = $this->send($client, 'POST', $path, $policy)->assertCreated()->json('data');
        $this->send($client, 'POST', "$path/{$first['id']}/publish", [])->assertStatus(409);
        $this->send($client, 'POST', "$path/{$second['id']}/publish", [])->assertOk();
        $corrupt = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.payments.presentation', 'version' => 3, 'state' => 'draft',
            'snapshot' => json_encode(['channels' => ['cod' => ['enabled' => true]]], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->send($client, 'POST', "$path/$corrupt/publish", [])->assertStatus(409);
        $revisions = app(WebsitePaymentPresentation::class)->revisions();
        $this->assertTrue($revisions['draft_invalid']);
        $this->assertNull($revisions['draft']);
        $this->assertSame($policy, $revisions['published']);
        $this->assertSame('published', DB::table('site_configuration_revisions')->where('id', $second['id'])->value('state'));
    }

    public function test_presentation_draft_requires_admin_csrf_and_keeps_credential_fields_out_of_the_page(): void
    {
        [$manager, $outlet] = $this->admin(['shops.enter', 'website.payments.manage', 'website.publish']);
        $client = $this->client();
        $this->login($client, $manager, $outlet);
        $policy = app(WebsitePaymentPresentation::class)->defaults();
        $path = '/internal/admin/website/payment-settings/presentation/drafts';
        $client['tokens'] = [];
        $this->send($client, 'POST', $path, $policy)->assertStatus(419);
        $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', 'website.payments.presentation')->count());
        $page = $this->send($client, 'GET', '/internal/admin/website/payment-settings')->assertOk();
        $this->assertStringContainsString('"presentation"', html_entity_decode($page->getContent(), ENT_QUOTES | ENT_HTML5));
        $this->assertStringNotContainsString('merchant_secret', $page->getContent());
        $this->assertContains('private', explode(', ', $page->headers->get('Cache-Control')));
        $this->assertContains('no-store', explode(', ', $page->headers->get('Cache-Control')));
    }

    public function test_separate_mysql_revision_lock_rejects_overlapping_presentation_writes_without_partial_rows(): void
    {
        [$publisher, $outlet] = $this->admin(['shops.enter', 'website.payments.manage', 'website.publish']);
        $client = $this->client();
        $this->login($client, $publisher, $outlet);
        $connectionName = 'w04_presentation_lock_holder';
        config(['database.connections.'.$connectionName => config('database.connections.mysql')]);
        $holder = DB::connection($connectionName);
        $lock = 'mobisttech.website.payments.presentation.revision';
        $path = '/internal/admin/website/payment-settings/presentation/drafts';
        try {
            $this->assertSame(1, (int) $holder->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired);
            $this->send($client, 'POST', $path, app(WebsitePaymentPresentation::class)->defaults())->assertStatus(409);
            $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', 'website.payments.presentation')->count());
        } finally {
            $holder->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            DB::disconnect($connectionName);
        }
    }

    private function admin(array $permissions): array
    {
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'W04 COD Admin', 'email' => Str::uuid().'@example.invalid',
            'password' => Hash::make('SyntheticPass123!'), 'permissions' => $permissions,
            'auth_version' => 1,
        ])->save();
        $outlet = new Outlet;
        $outlet->forceFill([
            'name' => 'W04 COD Outlet', 'public_id' => (string) Str::uuid(),
            'outlet_code' => (string) random_int(100, 999),
        ])->save();
        $admin->shops()->attach($outlet);

        return [$admin->fresh(), $outlet];
    }

    private function login(array &$client, Admin $admin, Outlet $outlet): void
    {
        $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $admin->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();
        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $outlet->public_id,
        ])->assertOk();
    }

    private function client(): array
    {
        // Four independent synthetic clients must not share one production 5/min/IP
        // identity throttle bucket. Keep all real throttles and request boundaries on.
        static $nextSyntheticIp = 20;
        $client = [
            'cookies' => [], 'tokens' => [], 'agent' => 'Synthetic W04 COD client',
            'ip' => '127.0.0.'.$nextSyntheticIp++,
        ];
        $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();

        return $client;
    }

    private function send(array &$client, string $method, string $uri, array $data = [])
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent'], 'REMOTE_ADDR' => $client['ip'],
        ];
        if (isset($client['tokens']['XSRF-TOKEN-admin'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN-admin'];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(
                    app('encrypter')->decrypt($cookie->getValue(), false),
                );
            }
        }

        return $response;
    }
}
