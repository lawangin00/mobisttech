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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class ClientProjectServicesTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private CustomerAccount $customer;

    private array $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        Storage::fake('local');
        config(['infrastructure.private_disk' => 'local', 'session.driver' => 'database']);
        $draft = app(WebsiteModePublication::class)->saveDraft($this->actor, 'digital_only');
        app(WebsiteModePublication::class)->publish($this->actor, $draft['id']);

        $this->customer = new CustomerAccount;
        $this->customer->forceFill([
            'public_id' => (string) Str::uuid(), 'name' => 'Project Client',
            'email' => 'project-client@example.invalid', 'mobile' => '03007770000',
            'password' => 'SyntheticPass123!', 'is_admin' => false,
        ])->save();
        $leads = app(DigitalServiceLeads::class);
        $leads->configureService($this->actor, [
            'slug' => 'project-service', 'name' => 'Project Service', 'short_description' => 'Project service',
            'price_type' => 'quote', 'price' => null, 'is_active' => true,
        ]);
        $this->lead = $leads->submit('mt36-lead', 'mt36-browser', [
            'service_slug' => 'project-service', 'customer_name' => 'Original Lead',
            'customer_mobile' => '03001110000', 'customer_email' => 'lead-contact@example.invalid',
            'requirements' => 'Need a scoped client project', 'source' => 'campaign-a',
        ]);
    }

    public function test_proposal_approval_revisions_and_exact_schedule_are_immutable(): void
    {
        $service = app(ClientProjectServices::class);
        $project = $this->project($service);
        $draft = $service->createProposal($this->actor, $project['public_id'], $this->proposalInput($project['version'], '1000.00'));
        $this->assertSame('draft', $draft['state']);
        $this->assertSame(['400.00', '600.00'], array_column($draft['schedule'], 'amount'));

        $approved = $service->approveProposal($this->actor, $draft['public_id']);
        $this->assertSame('approved', $approved['state']);
        $this->assertSame('1000.00', $approved['amount']);
        $this->assertCount(2, $approved['milestones']);
        $this->assertSame('1000.00', array_reduce($approved['milestones'], fn ($sum, $row) => bcadd($sum, $row['amount'], 2), '0.00'));
        $replay = $service->approveProposal($this->actor, $draft['public_id']);
        $this->assertSame($approved['quote']['public_id'], $replay['quote']['public_id']);
        $this->assertSame(1, DB::table('project_quotes')->count());

        $current = $service->adminProject($this->actor, $project['public_id']);
        $revision = $service->createProposal($this->actor, $project['public_id'], $this->proposalInput($current['version'], '1200.00'));
        $second = $service->approveProposal($this->actor, $revision['public_id']);
        $this->assertSame('1200.00', $second['amount']);
        $this->assertSame('superseded', DB::table('project_proposals')->where('public_id', $draft['public_id'])->value('state'));
        $this->assertSame('superseded', DB::table('project_quotes')->where('public_id', $approved['quote']['public_id'])->value('status'));
        $this->assertSame(2, DB::table('project_quotes')->count());
    }

    public function test_expired_or_tampered_proposals_cannot_be_approved(): void
    {
        $service = app(ClientProjectServices::class);
        $project = $this->project($service);

        $snapshotTampered = $service->createProposal($this->actor, $project['public_id'], $this->proposalInput($project['version'], '1000.00'));
        $snapshot = json_decode(DB::table('project_proposals')->where('public_id', $snapshotTampered['public_id'])->value('snapshot'), true, flags: JSON_THROW_ON_ERROR);
        $snapshot['scope'] = 'Tampered scope';
        DB::table('project_proposals')->where('public_id', $snapshotTampered['public_id'])->update([
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $this->reject(fn () => $service->approveProposal($this->actor, $snapshotTampered['public_id']));
        $this->assertSame(0, DB::table('project_quotes')->count());

        $current = $service->adminProject($this->actor, $project['public_id']);
        $columnTampered = $service->createProposal($this->actor, $project['public_id'], $this->proposalInput($current['version'], '1000.00'));
        DB::table('project_proposals')->where('public_id', $columnTampered['public_id'])->update(['amount' => '999.00']);
        $this->reject(fn () => $service->approveProposal($this->actor, $columnTampered['public_id']));
        $this->assertSame(0, DB::table('project_quotes')->count());

        $current = $service->adminProject($this->actor, $project['public_id']);
        $expired = $service->createProposal($this->actor, $project['public_id'], $this->proposalInput($current['version'], '1000.00'));
        $this->travel(15)->days();
        try {
            $this->reject(fn () => $service->approveProposal($this->actor, $expired['public_id']));
        } finally {
            $this->travelBack();
        }
        $this->assertSame(0, DB::table('project_quotes')->count());
    }

    public function test_payment_replay_owner_binding_and_paid_history_block_repricing(): void
    {
        $projects = app(ClientProjectServices::class);
        $project = $this->project($projects);
        $draft = $projects->createProposal($this->actor, $project['public_id'], $this->proposalInput($project['version'], '1000.00'));
        $approved = $projects->approveProposal($this->actor, $draft['public_id']);
        $fake = $this->fakeProvider();
        $orders = app(OrderTransactions::class);
        $firstMilestone = $approved['milestones'][0]['id'];
        $created = $orders->milestone($this->customer, $this->key('first'), ['milestone_id' => $firstMilestone, 'gateway' => 'jazzcash']);
        $replay = $orders->milestone($this->customer, $this->key('first'), ['milestone_id' => $firstMilestone, 'gateway' => 'jazzcash']);
        $this->assertSame($created['payment_id'], $replay['payment_id']);

        $other = $this->customer('other-project@example.invalid', '03008880000');
        $quoteId = DB::table('project_quotes')->where('public_id', $approved['quote']['public_id'])->value('id');
        DB::table('project_quotes')->where('id', $quoteId)->update(['customer_email' => $other->email, 'customer_mobile' => $other->mobile]);
        $this->reject(fn () => $orders->milestone($other, $this->key('wrong-owner'), ['milestone_id' => $firstMilestone, 'gateway' => 'jazzcash']));

        $init = $orders->initiate($created['payment_id']);
        $paidEvent = $fake->paid('MT36-EVT-1', $init['reference'], '400.00');
        $paid = $orders->callback('jazzcash', $paidEvent);
        $this->assertSame('paid', $paid['payment_status']);
        $this->assertSame('paid', $orders->callback('jazzcash', $paidEvent)['payment_status']);
        $this->assertNotNull(DB::table('project_milestone_identities')->where('id', $firstMilestone)->value('paid_at'));

        $current = $projects->adminProject($this->actor, $project['public_id']);
        $change = $projects->createProposal($this->actor, $project['public_id'], $this->proposalInput($current['version'], '1100.00'));
        $this->reject(fn () => $projects->approveProposal($this->actor, $change['public_id']));
        $this->assertSame('approved', DB::table('project_proposals')->where('public_id', $draft['public_id'])->value('state'));
    }

    public function test_completion_requires_all_milestones_and_mode_switch_preserves_portal_files(): void
    {
        $projects = app(ClientProjectServices::class);
        $project = $this->project($projects);
        $draft = $projects->createProposal($this->actor, $project['public_id'], $this->proposalInput($project['version'], '1000.00'));
        $approved = $projects->approveProposal($this->actor, $draft['public_id']);
        $fake = $this->fakeProvider();
        $orders = app(OrderTransactions::class);
        foreach ($approved['milestones'] as $index => $milestone) {
            if ($index === 1) {
                break;
            }
            $created = $orders->milestone($this->customer, $this->key('paid-'.$index), ['milestone_id' => $milestone['id'], 'gateway' => 'jazzcash']);
            $init = $orders->initiate($created['payment_id']);
            $orders->callback('jazzcash', $fake->paid('MT36-P-'.$index, $init['reference'], $milestone['amount']));
        }
        $current = $projects->adminProject($this->actor, $project['public_id']);
        $current = $projects->transition($this->actor, $project['public_id'], ['version' => $current['version'], 'status' => 'in_progress']);
        $current = $projects->transition($this->actor, $project['public_id'], ['version' => $current['version'], 'status' => 'delivered']);
        $this->reject(fn () => $projects->transition($this->actor, $project['public_id'], ['version' => $current['version'], 'status' => 'completed']));

        $second = $approved['milestones'][1];
        $created = $orders->milestone($this->customer, $this->key('paid-final'), ['milestone_id' => $second['id'], 'gateway' => 'jazzcash']);
        $init = $orders->initiate($created['payment_id']);
        $orders->callback('jazzcash', $fake->paid('MT36-P-FINAL', $init['reference'], $second['amount']));
        $completed = $projects->transition($this->actor, $project['public_id'], ['version' => $current['version'], 'status' => 'completed']);
        $this->assertSame('completed', $completed['status']);
        $reference = $projects->uploadReference($this->customer, $project['public_id'], ['name' => 'brief.txt', 'contents' => 'private project brief']);
        $delivery = $projects->uploadDelivery($this->actor, $project['public_id'], ['name' => 'delivery.txt', 'contents' => 'private delivery']);
        $this->assertArrayNotHasKey('object_key', $reference);
        $this->assertArrayNotHasKey('object_key', $delivery);
        $this->assertCount(2, Storage::disk('local')->allFiles('client-files'));

        $modes = app(WebsiteModePublication::class);
        $modeDraft = $modes->saveDraft($this->actor, 'commerce_only');
        $modes->publish($this->actor, $modeDraft['id']);
        $portal = $projects->portal($this->customer, $project['public_id']);
        $this->assertSame('completed', $portal['status']);
        $this->assertNotContains('proposal_drafted', array_column($portal['history'], 'type'));
        $download = $projects->download($this->customer, $project['public_id'], $delivery['id']);
        $this->assertSame('private delivery', $download['contents']);
        $other = $this->customer('portal-other@example.invalid', '03009990000');
        $this->reject(fn () => $projects->portal($other, $project['public_id']));
    }

    public function test_customer_project_api_is_owner_scoped_and_exposes_only_configured_project_payment_channels(): void
    {
        $projects = app(ClientProjectServices::class);
        $project = $this->project($projects);
        $draft = $projects->createProposal($this->actor, $project['public_id'], $this->proposalInput($project['version'], '1000.00'));
        $approved = $projects->approveProposal($this->actor, $draft['public_id']);
        $this->fakeProvider();

        $client = $this->customerClient();
        $this->customerLogin($client, $this->customer->email)->assertOk();

        $this->customerSend($client, 'GET', '/api/v1/projects')
            ->assertOk()->assertJsonPath('data.items.0.public_id', $project['public_id']);
        $this->customerSend($client, 'GET', '/api/v1/projects/'.$project['public_id'])
            ->assertOk()->assertJsonPath('data.public_id', $project['public_id']);

        $channels = $this->customerSend($client, 'GET', '/api/v1/project-payment-channels')->assertOk();
        $channels->assertJsonMissing(['code' => 'cod'])->assertJsonFragment(['code' => 'jazzcash']);

        $upload = $this->customerSend($client, 'POST', '/api/v1/projects/'.$project['public_id'].'/files/reference', [
            'name' => 'api-brief.txt', 'base64' => base64_encode('private API brief'),
        ])->assertCreated()->assertJsonPath('data.name', 'api-brief.txt');
        $fileId = $upload->json('data.id');
        $download = $this->customerSend($client, 'GET', '/api/v1/projects/'.$project['public_id'].'/files/'.$fileId)->assertOk();
        $this->assertSame('private API brief', $download->getContent());

        $milestone = $approved['milestones'][0]['id'];
        $payment = $this->customerSend($client, 'POST', '/api/v1/project-milestones/pay', [
            'milestone_id' => $milestone, 'gateway' => 'jazzcash',
        ], ['Idempotency-Key' => $this->key('api-payment')])->assertCreated();
        $this->assertNotNull($payment->json('data.payment_id'));

        $other = $this->customer('api-other@example.invalid', '03006660000');
        $otherClient = $this->customerClient();
        $this->customerLogin($otherClient, $other->email)->assertOk();
        $this->customerSend($otherClient, 'GET', '/api/v1/projects')->assertOk()->assertJsonCount(0, 'data.items');
        $this->customerSend($otherClient, 'GET', '/api/v1/projects/'.$project['public_id'])->assertNotFound();
        $this->customerSend($otherClient, 'GET', '/api/v1/projects/'.$project['public_id'].'/files/'.$fileId)->assertNotFound();
        $beforeForeignMilestone = [DB::table('orders')->count(), DB::table('payments')->count()];
        $this->customerSend($otherClient, 'POST', '/api/v1/project-milestones/pay', [
            'milestone_id' => $milestone, 'gateway' => 'jazzcash',
        ], ['Idempotency-Key' => $this->key('foreign-owner')])->assertStatus(409);
        $this->assertSame($beforeForeignMilestone, [DB::table('orders')->count(), DB::table('payments')->count()]);
        $this->customerSend($otherClient, 'POST', '/api/v1/projects/'.$project['public_id'].'/files/reference', [
            'name' => 'forbidden.txt', 'base64' => base64_encode('blocked'),
        ])->assertNotFound();
    }

    public function test_conversion_reporting_is_aggregate_and_permissions_are_separate(): void
    {
        $projects = app(ClientProjectServices::class);
        $project = $this->project($projects);
        $draft = $projects->createProposal($this->actor, $project['public_id'], $this->proposalInput($project['version'], '1000.00'));
        $projects->approveProposal($this->actor, $draft['public_id']);
        $summary = $projects->conversionSummary($this->actor, now()->subDay()->toIso8601String(), now()->addDay()->toIso8601String());
        $this->assertTrue($summary['aggregate_only']);
        $this->assertSame(1, $summary['totals']['leads']);
        $this->assertSame(1, $summary['totals']['projects']);
        $this->assertSame(1, $summary['totals']['approved_proposals']);
        $encoded = json_encode($summary, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($this->customer->email, $encoded);
        $this->assertStringNotContainsString($this->customer->mobile, $encoded);
        $this->assertStringNotContainsString($this->customer->name, $encoded);

        $viewer = $this->adminWith([]);
        $this->reject(fn () => $projects->adminProject($viewer, $project['public_id']));
        $this->reject(fn () => $projects->conversionSummary($viewer, now()->subDay()->toIso8601String(), now()->addDay()->toIso8601String()));
        $approverOnly = $this->adminWith(['website.proposals.approve']);
        $this->reject(fn () => $projects->adminProject($approverOnly, $project['public_id']));
    }

    private function project(ClientProjectServices $service): array
    {
        return $service->createProject($this->actor, $this->lead['public_id'], [
            'title' => 'Synthetic client project', 'customer_account_public_id' => $this->customer->public_id,
            'assigned_admin_public_id' => $this->actor->public_id,
        ]);
    }

    private function proposalInput(int $version, string $amount): array
    {
        $deposit = bcdiv(bcmul($amount, '0.40', 2), '1', 2);
        $final = bcsub($amount, $deposit, 2);

        return [
            'version' => $version, 'title' => 'Approved project scope', 'scope' => 'Defined synthetic scope.',
            'deliverables' => ['Working deliverable', 'Handover notes'], 'amount' => $amount,
            'valid_until' => now()->addDays(14)->toIso8601String(),
            'milestones' => [
                ['kind' => 'deposit', 'label' => 'Deposit', 'amount' => $deposit, 'due_at' => now()->addDays(3)->toIso8601String()],
                ['kind' => 'final', 'label' => 'Final payment', 'amount' => $final, 'due_at' => now()->addDays(10)->toIso8601String()],
            ],
        ];
    }

    private function customer(string $email, string $mobile): CustomerAccount
    {
        $customer = new CustomerAccount;
        $customer->forceFill([
            'public_id' => (string) Str::uuid(), 'name' => 'Other Client', 'email' => $email,
            'mobile' => $mobile, 'password' => 'SyntheticPass123!', 'is_admin' => false,
        ])->save();

        return $customer;
    }

    private function customerClient(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'MT-5.5 customer API'];
        $response = $this->customerSend($client, 'GET', '/api/v1/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function customerLogin(array &$client, string $email)
    {
        return $this->customerSend($client, 'POST', '/api/v1/auth/login', [
            'email' => $email, 'password' => 'SyntheticPass123!',
        ]);
    }

    private function customerSend(array &$client, string $method, string $uri, array $data = [], array $headers = [])
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => $client['agent']];
        if (isset($client['tokens']['XSRF-TOKEN'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN'];
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if ($cookie->getName() === 'XSRF-TOKEN') {
                $client['tokens']['XSRF-TOKEN'] = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
            }
        }

        return $response;
    }

    private function adminWith(array $permissions): Admin
    {
        $admin = new Admin;
        $admin->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Project actor '.Str::uuid(),
            'email' => Str::uuid().'@example.invalid', 'password' => 'SyntheticPass123!', 'permissions' => $permissions])->save();

        return $admin;
    }

    private function fakeProvider(): ProjectFakePaymentProvider
    {
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $fake = new ProjectFakePaymentProvider;
        $registry = new PaymentProviders;
        $registry->register('jazzcash', $fake);
        $this->app->instance(PaymentProviders::class, $registry);

        return $fake;
    }

    private function key(string $suffix): string
    {
        return 'mt36-'.$suffix.'-'.str_repeat('x', 24);
    }
}

final class ProjectFakePaymentProvider implements PaymentProvider
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
