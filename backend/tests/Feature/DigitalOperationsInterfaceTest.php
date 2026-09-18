<?php

namespace Tests\Feature;

use App\Cms\WebsiteModePublication;
use App\Commerce\OrderTransactions;
use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use App\Digital\ClientProjectServices;
use App\Digital\DigitalServiceLeads;
use App\Models\Admin;
use App\Models\CustomerAccount;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class DigitalOperationsInterfaceTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private const PASSWORD = 'SyntheticPass123!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        config(['session.driver' => 'database', 'infrastructure.private_disk' => 'local']);
        Storage::fake('local');
        $draft = app(WebsiteModePublication::class)->saveDraft($this->actor, 'digital_only');
        app(WebsiteModePublication::class)->publish($this->actor, $draft['id']);
    }

    public function test_data_and_conversion_routes_are_permission_scoped_and_analytics_are_aggregate_only(): void
    {
        [$lead, $project, $customer] = $this->fixtureProject();
        $manager = $this->admin($this->digitalPermissions());
        $client = $this->client();
        $this->login($client, $manager->email)->assertOk();

        $data = $this->send($client, 'GET', '/internal/admin/digital-operations/data')->assertOk();
        $data->assertJsonPath('data.website_mode.mode', 'digital_only')
            ->assertJsonPath('data.leads.0.public_id', $lead['public_id'])
            ->assertJsonPath('data.projects.0.public_id', $project['public_id']);
        $payload = $data->getContent();
        $this->assertStringContainsString('Original Lead', $payload);
        $this->assertStringNotContainsString('object_key', $payload);
        $this->assertStringNotContainsString('client-files/', $payload);
        $this->assertTrue(collect($data->json('data.mode_history'))->contains(fn ($row) => ($row['mode'] ?? null) === 'digital_only'));

        $from = rawurlencode(now()->subDay()->toIso8601String());
        $to = rawurlencode(now()->addDay()->toIso8601String());
        $summary = $this->send($client, 'GET', "/internal/admin/digital-operations/conversions?from={$from}&to={$to}")->assertOk();
        $summary->assertJsonPath('data.aggregate_only', true)
            ->assertJsonPath('data.totals.leads', 1)
            ->assertJsonPath('data.totals.projects', 1);
        $analytics = $summary->getContent();
        foreach ([$customer->name, $customer->email, $customer->mobile, 'Need a scoped client project'] as $secret) {
            $this->assertStringNotContainsString($secret, $analytics);
        }

        $viewer = $this->admin([]);
        $viewerClient = $this->client();
        $this->login($viewerClient, $viewer->email)->assertOk();
        $this->send($viewerClient, 'GET', '/internal/admin/digital-operations/data')->assertForbidden();

        $reporter = $this->admin(['website.conversions.view']);
        $reportClient = $this->client();
        $this->login($reportClient, $reporter->email)->assertOk();
        $reportData = $this->send($reportClient, 'GET', '/internal/admin/digital-operations/data')->assertOk()->json('data');
        $this->assertSame([], $reportData['leads']);
        $this->assertSame([], $reportData['projects']);
        $this->assertSame([], $reportData['services']);
        $this->send($reportClient, 'GET', "/internal/admin/digital-operations/conversions?from={$from}&to={$to}")
            ->assertOk()->assertJsonPath('data.aggregate_only', true);
    }

    public function test_private_lead_and_project_downloads_are_authorized_integrity_checked_and_storage_keys_stay_private(): void
    {
        [$lead, $project] = $this->fixtureProject(true);
        $projects = app(ClientProjectServices::class);
        $customer = CustomerAccount::query()->where('public_id', $project['customer_account_public_id'])->firstOrFail();
        $delivery = $projects->uploadReference($customer, $project['public_id'], [
            'name' => 'project-reference.txt', 'contents' => 'private project reference',
        ]);
        $leadDetail = app(DigitalServiceLeads::class)->lead($this->actor, $lead['public_id']);
        $leadFile = $leadDetail['reference_files'][0];

        $manager = $this->admin(['website.digital-leads.manage', 'website.digital-projects.manage', 'website.client-files.manage']);
        $client = $this->client();
        $this->login($client, $manager->email)->assertOk();

        $download = $this->send($client, 'GET', '/internal/admin/digital-operations/leads/'.$lead['public_id'].'/files/'.$leadFile['id']);
        $download->assertOk();
        $this->assertSame('project brief', $download->getContent());
        $cacheControl = (string) $download->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        $projectResponse = $this->send($client, 'GET', '/internal/admin/digital-operations/projects/'.$project['public_id'])->assertOk();
        $this->assertStringNotContainsString('object_key', $projectResponse->getContent());
        $this->assertStringNotContainsString('client-files/', $projectResponse->getContent());
        $projectDownload = $this->send($client, 'GET', '/internal/admin/digital-operations/projects/'.$project['public_id'].'/files/'.$delivery['id']);
        $projectDownload->assertOk();
        $this->assertSame('private project reference', $projectDownload->getContent());

        $leadRow = DB::table('service_request_files')->where('id', $leadFile['id'])->firstOrFail();
        Storage::disk('local')->put($leadRow->object_key, 'tampered content');
        $this->send($client, 'GET', '/internal/admin/digital-operations/leads/'.$lead['public_id'].'/files/'.$leadFile['id'])
            ->assertServerError();

        $leadOnly = $this->admin(['website.digital-leads.manage']);
        $leadOnlyClient = $this->client();
        $this->login($leadOnlyClient, $leadOnly->email)->assertOk();
        $this->send($leadOnlyClient, 'GET', '/internal/admin/digital-operations/projects/'.$project['public_id'].'/files/'.$delivery['id'])
            ->assertForbidden();
    }

    public function test_approved_monetary_snapshot_is_exact_and_paid_history_blocks_repricing(): void
    {
        [, $project, $customer] = $this->fixtureProject();
        $projects = app(ClientProjectServices::class);
        $draft = $projects->createProposal($this->actor, $project['public_id'], $this->proposalInput($project['version'], '1000.00'));

        $approver = $this->admin(['website.proposals.approve', 'website.digital-projects.manage']);
        $client = $this->client();
        $this->login($client, $approver->email)->assertOk();
        $approved = $this->send($client, 'POST', '/internal/admin/digital-operations/proposals/'.$draft['public_id'].'/approve')
            ->assertOk()->json('data');

        $this->assertSame('approved', $approved['state']);
        $this->assertSame('1000.00', $approved['amount']);
        $this->assertSame(['400.00', '600.00'], array_column($approved['schedule'], 'amount'));
        $this->assertSame('1000.00', array_reduce($approved['milestones'], fn ($sum, $row) => bcadd($sum, $row['amount'], 2), '0.00'));

        $fake = $this->fakeProvider();
        $orders = app(OrderTransactions::class);
        $milestone = $approved['milestones'][0];
        $payment = $orders->milestone($customer, $this->key('paid'), ['milestone_id' => $milestone['id'], 'gateway' => 'jazzcash']);
        $init = $orders->initiate($payment['payment_id']);
        $orders->callback('jazzcash', $fake->paid('MT48-PAID-1', $init['reference'], $milestone['amount']));
        $this->assertNotNull(DB::table('project_milestone_identities')->where('id', $milestone['id'])->value('paid_at'));

        $current = $projects->adminProject($this->actor, $project['public_id']);
        $revision = $projects->createProposal($this->actor, $project['public_id'], $this->proposalInput($current['version'], '1200.00'));
        $this->send($client, 'POST', '/internal/admin/digital-operations/proposals/'.$revision['public_id'].'/approve')
            ->assertStatus(409);
        $this->assertSame('approved', DB::table('project_proposals')->where('public_id', $draft['public_id'])->value('state'));
        $this->assertSame('400.00', DB::table('project_milestone_identities')->where('id', $milestone['id'])->value('approved_amount'));
    }

    private function fixtureProject(bool $withLeadFile = false): array
    {
        $leads = app(DigitalServiceLeads::class);
        $leads->configureService($this->actor, [
            'slug' => 'mt48-service', 'name' => 'MT48 Service', 'short_description' => 'Synthetic digital service',
            'price_type' => 'quote', 'price' => null, 'is_active' => true,
        ]);
        $files = $withLeadFile ? [['name' => 'brief.txt', 'contents' => 'project brief']] : [];
        $lead = $leads->submit('mt48-'.Str::random(8), 'mt48-browser-'.Str::random(8), [
            'service_slug' => 'mt48-service', 'customer_name' => 'Original Lead',
            'customer_mobile' => '03001110000', 'customer_email' => 'lead-contact@example.invalid',
            'requirements' => 'Need a scoped client project', 'source' => 'campaign-mt48',
        ], $files);
        $customer = new CustomerAccount;
        $customer->forceFill([
            'public_id' => (string) Str::uuid(), 'name' => 'MT48 Client',
            'email' => 'mt48-client-'.Str::random(6).'@example.invalid', 'mobile' => '0300777'.random_int(1000, 9999),
            'password' => self::PASSWORD, 'is_admin' => false,
        ])->save();
        $project = app(ClientProjectServices::class)->createProject($this->actor, $lead['public_id'], [
            'title' => 'MT48 Client Project', 'customer_account_public_id' => $customer->public_id,
            'assigned_admin_public_id' => $this->actor->public_id,
        ]);

        return [$lead, $project, $customer];
    }

    private function proposalInput(int $version, string $amount): array
    {
        $deposit = bcdiv(bcmul($amount, '0.40', 2), '1', 2);
        $final = bcsub($amount, $deposit, 2);

        return [
            'version' => $version, 'title' => 'MT48 approved scope', 'scope' => 'Defined MT48 scope.',
            'deliverables' => ['Working deliverable', 'Handover notes'], 'amount' => $amount,
            'valid_until' => now()->addDays(14)->toIso8601String(),
            'milestones' => [
                ['kind' => 'deposit', 'label' => 'Deposit', 'amount' => $deposit, 'due_at' => now()->addDays(3)->toIso8601String()],
                ['kind' => 'final', 'label' => 'Final payment', 'amount' => $final, 'due_at' => now()->addDays(10)->toIso8601String()],
            ],
        ];
    }

    private function digitalPermissions(): array
    {
        return [
            'website.services.manage', 'website.consultations.manage', 'website.digital-leads.manage',
            'website.digital-projects.manage', 'website.proposals.approve', 'website.client-files.manage',
            'website.conversions.view',
        ];
    }

    private function admin(array $permissions): Admin
    {
        $admin = new Admin;
        $admin->forceFill([
            'public_id' => (string) Str::uuid(), 'name' => 'MT48 Admin '.Str::random(5),
            'email' => Str::uuid().'@example.invalid', 'password' => Hash::make(self::PASSWORD),
            'permissions' => $permissions, 'auth_version' => 1,
        ])->save();

        return $admin->fresh();
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic MT-4.8 browser'];
        $response = $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $email)
    {
        return $this->send($client, 'POST', '/internal/admin/auth/login', ['email' => $email, 'password' => self::PASSWORD]);
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true)
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => $client['agent']];
        if ($csrf && isset($client['tokens']['XSRF-TOKEN-admin'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN-admin'];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
            }
        }

        return $response;
    }

    private function fakeProvider(): MT48FakePaymentProvider
    {
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $fake = new MT48FakePaymentProvider;
        $registry = new PaymentProviders;
        $registry->register('jazzcash', $fake);
        $this->app->instance(PaymentProviders::class, $registry);

        return $fake;
    }

    private function key(string $suffix): string
    {
        return 'mt48-'.$suffix.'-'.str_repeat('x', 24);
    }
}

final class MT48FakePaymentProvider implements PaymentProvider
{
    public function initiate(array $intent): array
    {
        return ['reference' => 'GW-'.$intent['payment_id'], 'redirect_url' => 'https://gateway.example.invalid/pay/'.$intent['payment_id']];
    }

    public function verify(array $payload): array
    {
        $signature = $payload['signature'] ?? '';
        $unsigned = array_diff_key($payload, ['signature' => true]);
        if (! hash_equals(hash_hmac('sha256', json_encode($unsigned, JSON_THROW_ON_ERROR), 'synthetic-secret'), $signature)) {
            throw new LogicException('Invalid synthetic provider signature.');
        }

        return $unsigned;
    }

    public function paid(string $event, string $reference, string $amount): array
    {
        $payload = [
            'event_id' => $event, 'transaction_reference' => 'TX-'.$event,
            'order_reference' => $reference, 'amount' => $amount, 'currency' => 'PKR', 'status' => 'paid',
            'payload_hash' => hash('sha256', $event.'|'.$reference.'|'.$amount.'|paid'),
        ];
        $payload['signature'] = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'synthetic-secret');

        return $payload;
    }
}
