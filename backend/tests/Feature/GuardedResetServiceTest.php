<?php

namespace Tests\Feature;

use App\Addendum\ResetDomains;
use App\Identity\RealmSessionPolicy;
use App\Infrastructure\PrivateObjects;
use App\Models\Admin;
use App\Models\User;
use App\Operations\GuardedResetService;
use App\Operations\ResetObjectBackup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuardedResetServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_preview_requires_recent_auth_and_retained_dependency_blocks_execution(): void
    {
        $actor = $this->resetActor();
        $service = app(GuardedResetService::class);
        $this->reject(fn () => $service->preview($actor, $this->request(false), 'transactional', ['digital_projects']));
        $quote = DB::table('project_quotes')->insertGetId([
            'reference' => 'RST-'.Str::uuid(), 'client_name' => 'Synthetic', 'customer_mobile' => '03000000000',
            'title' => 'Reset barrier', 'amount' => '100.00', 'public_id' => (string) Str::uuid(),
        ]);
        $order = DB::table('orders')->insertGetId([
            'order_number' => 'RST-'.Str::uuid(), 'order_type' => 'digital', 'customer_name' => 'Synthetic',
            'customer_mobile' => '03000000000', 'public_id' => (string) Str::uuid(),
        ]);
        DB::table('order_items')->insert([
            'order_id' => $order, 'item_type' => 'digital', 'title' => 'Retained order item',
            'project_quote_id' => $quote, 'quantity' => 1, 'unit_price' => '100.00', 'line_total' => '100.00',
        ]);
        $preview = $service->preview($actor, $this->request(), 'transactional', ['digital_projects']);
        $this->assertTrue(collect($preview['barriers'])->contains(fn ($row) => $row['type'] === 'retained_reference'
            && $row['retained_child'] === 'order_items' && $row['selected_parent'] === 'project_quotes'));
        $this->assertSame('TRANSACTIONAL DATA RESET', $preview['confirmation_required']);
        $this->reject(fn () => $service->execute($actor, $this->request(), $preview['public_id'], 'WRONG CONFIRMATION'));
        $this->assertSame(0, DB::table('backup_records')->where('scope', 'reset')->where('requested_by_id', $actor->id)->count());
        $this->assertSame('previewed', DB::table('reset_operations')->where('public_id', $preview['public_id'])->value('status'));
    }

    public function test_stale_preview_stops_before_backup(): void
    {
        $actor = $this->resetActor();
        $service = app(GuardedResetService::class);
        [$digitalService] = $this->serviceRequest();
        $preview = $service->preview($actor, $this->request(), 'transactional', ['service_requests']);
        $this->serviceRequest($digitalService);
        $this->reject(fn () => $service->execute(
            $actor, $this->request(), $preview['public_id'], 'TRANSACTIONAL DATA RESET'
        ));
        $this->assertSame(0, DB::table('backup_records')->where('scope', 'reset')->where('requested_by_id', $actor->id)->count());
        $this->assertSame('previewed', DB::table('reset_operations')->where('public_id', $preview['public_id'])->value('status'));
    }

    public function test_transactional_reset_requires_verified_backup_and_reconciles_private_objects(): void
    {
        $actor = $this->resetActor();
        $service = app(GuardedResetService::class);
        [$digitalService, $requestId] = $this->serviceRequest();
        $objectId = (string) Str::uuid();
        $objectKey = 'media/'.$objectId.'.pdf';
        $bytes = 'synthetic-private-reference';
        app(PrivateObjects::class)->put($objectKey, $bytes, hash('sha256', $bytes));
        DB::table('service_request_files')->insert([
            'id' => $objectId, 'service_request_id' => $requestId, 'object_key' => $objectKey,
            'original_name' => 'reference.pdf', 'mime_type' => 'application/pdf', 'byte_size' => strlen($bytes),
            'sha256' => hash('sha256', $bytes), 'uploaded_at' => now(),
        ]);
        try {
            $preview = $service->preview($actor, $this->request(), 'transactional', ['service_requests']);
            $this->assertSame(1, $preview['file_count']);
            $this->assertSame([], $preview['barriers']);
            $result = $service->execute($actor, $this->request(), $preview['public_id'], 'TRANSACTIONAL DATA RESET');
            $this->assertSame('completed', $result['status']);
            $this->assertFalse(DB::table('service_requests')->where('id', $requestId)->exists());
            $this->assertTrue(DB::table('digital_services')->where('id', $digitalService)->exists());
            $this->assertFalse(app(PrivateObjects::class)->exists($objectKey));
            $operation = DB::table('reset_operations')->where('public_id', $preview['public_id'])->firstOrFail();
            $this->assertSame('completed', $operation->status);
            $this->assertNotNull($operation->backup_record_id);
            $this->assertNotNull(DB::table('backup_manifests')->where('backup_record_id', $operation->backup_record_id)->value('verified_at'));
            $this->assertSame('passed', DB::table('backup_restore_rehearsals')->where('backup_record_id', $operation->backup_record_id)->value('status'));
            $manifest = json_decode($operation->object_backup_manifest, true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(1, $manifest['count']);
            $this->assertTrue(app(PrivateObjects::class)->exists($manifest['items'][0]['backup_key']));
            app(ResetObjectBackup::class)->verify($manifest);
            $this->assertTrue(DB::table('admin_audit_logs')->where('action', 'reset_completed')->exists());
        } finally {
            $this->cleanupResetBackups($actor->id);
            if (app(PrivateObjects::class)->exists($objectKey)) {
                app(PrivateObjects::class)->delete($objectKey);
            }
        }
    }

    public function test_business_and_factory_reset_preserve_bootstrap_and_reset_customer_and_configuration_rows(): void
    {
        $actor = $this->resetActor();
        $service = app(GuardedResetService::class);
        try {
            $customer = $this->customer('business-reset');
            $preview = $service->preview($actor, $this->request(), 'business', ['customers']);
            $this->assertSame([], $preview['barriers']);
            $service->execute($actor, $this->request(), $preview['public_id'], 'BUSINESS DATA RESET');
            $this->assertFalse(DB::table('users')->where('id', $customer->id)->exists());
            $this->assertTrue(DB::table('admins')->where('id', $actor->id)->exists());

            $factoryCustomer = $this->customer('factory-reset');
            DB::table('site_settings')->insert([
                'key' => 'reset.synthetic', 'value' => 'custom', 'group' => 'general', 'label' => 'Synthetic',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->reject(fn () => $service->preview($actor, $this->request(), 'factory', ['customers']));
            $factoryDomains = app(ResetDomains::class)->available('factory');
            $factoryPreview = $service->preview($actor, $this->request(), 'factory', $factoryDomains);
            $this->assertSame([], $factoryPreview['barriers']);
            $factoryResult = $service->execute($actor, $this->request(), $factoryPreview['public_id'], 'FACTORY RESET');
            $this->assertSame('completed', $factoryResult['status']);
            $this->assertFalse(DB::table('users')->where('id', $factoryCustomer->id)->exists());
            $this->assertFalse(DB::table('site_settings')->where('key', 'reset.synthetic')->exists());
            $this->assertTrue(DB::table('admins')->where('id', $actor->id)->exists());
            $this->assertSame(1, DB::table('business_profiles')->count());
            $this->assertSame(2, DB::table('integration_connections')->count());
            $this->assertSame(12, DB::table('roles')->count());
            $this->assertSame(6, DB::table('document_template_revisions')->count());
            $this->assertGreaterThan(0, DB::table('permission_definitions')->count());
            $this->assertSame(2, DB::table('reset_operations')->where('actor_admin_id', $actor->id)->where('status', 'completed')->count());
        } finally {
            $this->cleanupResetBackups($actor->id);
        }
    }

    private function resetActor(): Admin
    {
        $actor = new Admin;
        $actor->forceFill([
            'name' => 'Reset Owner', 'email' => 'reset-'.Str::uuid().'@example.invalid',
            'password' => 'SyntheticPass123!',
            'permissions' => ['system.reset.preview', 'system.reset.transactional', 'system.reset.business', 'system.reset.factory'],
        ])->save();

        return $actor;
    }

    private function request(bool $recent = true): Request
    {
        $request = Request::create('/admin/reset', 'POST');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        if ($recent) {
            app(RealmSessionPolicy::class)->confirmRecentAuthentication($request);
        }

        return $request;
    }

    private function customer(string $label): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'Reset Customer', 'email' => $label.'-'.Str::uuid().'@example.invalid',
            'password' => 'SyntheticPass123!', 'is_admin' => false, 'public_id' => (string) Str::uuid(),
        ])->save();

        return $user;
    }

    private function serviceRequest(?int $digitalServiceId = null): array
    {
        $digitalServiceId ??= DB::table('digital_services')->insertGetId([
            'slug' => 'reset-'.Str::lower(Str::random(10)),
            'name' => 'Reset Service', 'short_description' => 'Synthetic reset fixture',
            'price_type' => 'quote', 'is_active' => true, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $requestId = DB::table('service_requests')->insertGetId([
            'reference' => 'RST-'.Str::uuid(), 'digital_service_id' => $digitalServiceId,
            'customer_name' => 'Reset Lead', 'customer_mobile' => '03000000000',
            'requirements' => 'Synthetic reset fixture', 'preferred_contact' => 'whatsapp',
            'status' => 'new', 'public_id' => (string) Str::uuid(), 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$digitalServiceId, $requestId];
    }

    private function cleanupResetBackups(int $actorId): void
    {
        foreach (DB::table('reset_operations')->where('actor_admin_id', $actorId)->get() as $operation) {
            if ($operation->object_backup_manifest) {
                $manifest = json_decode($operation->object_backup_manifest, true);
                foreach ($manifest['items'] ?? [] as $item) {
                    if (isset($item['backup_key']) && app(PrivateObjects::class)->exists($item['backup_key'])) {
                        app(PrivateObjects::class)->delete($item['backup_key']);
                    }
                }
            }
        }
        foreach (DB::table('backup_records')->where('scope', 'reset')->where('requested_by_id', $actorId)->pluck('path') as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function reject(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $error) {
            $this->assertNotEmpty($error::class);

            return;
        }
        $this->fail('Unsafe reset operation unexpectedly succeeded.');
    }
}
