<?php

namespace Tests\Feature;

use App\Models\Outlet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OutletArchiveRowLockTest extends TestCase
{
    public function test_two_connections_serialize_outlet_archive_lock_and_reobserve_archived_state(): void
    {
        // Intentionally no DatabaseTransactions: second MySQL connection must see the committed fixture.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403,
            'Archive concurrency probe is isolated-test-database only.');
        $id = (string) Str::uuid();
        $outlet = new Outlet;
        $outlet->forceFill(['public_id' => $id, 'name' => 'D03 isolated row-lock probe',
            'status' => false, 'version' => 1])->save();
        config(['database.connections.d03_archive_probe' => config('database.connections.mysql')]);
        $other = DB::connection('d03_archive_probe');
        try {
            $other->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::beginTransaction();
            try {
                $first = DB::table('outlets')->where('id', $outlet->id)->lockForUpdate()->firstOrFail();
                $this->assertNull($first->archived_at);
                try {
                    $other->transaction(fn () => $other->table('outlets')
                        ->where('id', $outlet->id)->lockForUpdate()->firstOrFail());
                    $this->fail('Independent writer unexpectedly bypassed the outlet archive row lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                }
                // The same row locked by OutletLifecycleAdministration::archive is committed archived.
                DB::table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::commit();
            } finally {
                if (DB::transactionLevel() > 0) { DB::rollBack(); }
            }
            $observed = $other->transaction(fn () => $other->table('outlets')
                ->where('id', $outlet->id)->lockForUpdate()->firstOrFail());
            $this->assertNotNull($observed->archived_at);
            $this->assertSame(2, (int) $observed->version);
        } finally {
            $other->disconnect();
            DB::purge('d03_archive_probe');
            DB::table('outlets')->where('public_id', $id)
                ->where('name', 'D03 isolated row-lock probe')->delete();
        }
    }

    public function test_real_archive_blocks_concurrent_cash_open_service_and_rejects_post_commit_retry(): void
    {
        // This fixture is committed so each real MySQL connection can see it; never run on a live database.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $fallback = new Outlet;
        $fallback->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'D03 service fallback '.$tag,
            'status' => false, 'version' => 1])->save();
        $historical = new Outlet;
        $historical->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'D03 service archive '.$tag,
            'status' => false, 'version' => 1])->save();
        $owner = new \App\Models\Admin;
        $owner->forceFill(['name' => 'D03 synthetic archive owner', 'email' => 'd03-owner-'.$tag.'@example.invalid',
            'password' => 'not-a-live-password', 'permissions' => ['shops.enter',
                'team-members.full-access.assign', 'admin.business-profile.manage']])->save();
        $owner->roles()->attach(\App\Models\Role::where('name', 'Full Access')->firstOrFail()->id,
            ['assigned_at' => now()]);
        $owner->shops()->attach($fallback);
        $cashActor = new \App\Models\Admin;
        $cashActor->forceFill(['name' => 'D03 synthetic cash actor', 'email' => 'd03-cash-'.$tag.'@example.invalid',
            'password' => 'not-a-live-password', 'permissions' => ['shops.enter', 'shop.cash']])->save();
        $cashActor->shops()->attach($historical);
        $originalDefault = DB::getDefaultConnection();
        config(['database.connections.d03_service_writer' => config('database.connections.mysql')]);
        $writer = DB::connection('d03_service_writer');
        $historicalSessionId = null;
        try {
            // Actual closed cash history remains eligible for the real archival service.
            $historyOpenKey = 'd03-cash-history-open-'.$tag;
            $opened = app(\App\Cash\CashSessionOperations::class)->open(
                $cashActor, $historical, $historyOpenKey, ['opening_cash' => '10.00']);
            $historicalSessionId = $opened['session_id'];
            $closed = app(\App\Cash\CashSessionOperations::class)->close(
                $cashActor, $historical, $historicalSessionId, 'd03-cash-history-close-'.$tag,
                ['session_version' => 1, 'actual_cash' => '10.00']);
            $this->assertSame('closed', $closed['status']);
            $historyBefore = DB::table('cash_sessions')->where('public_id', $historicalSessionId)->firstOrFail();
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                // The actual archive service holds its outlet lock in an outer, uncommitted transaction.
                $result = app(\App\Identity\OutletLifecycleAdministration::class)
                    ->archive($owner, $historical->public_id, 1);
                $this->assertSame('archived', $result['status']);
                DB::setDefaultConnection('d03_service_writer');
                $this->assertNull($writer->table('outlets')->where('id', $historical->id)->value('archived_at'));
                // Explicitly bind BOTH Eloquent models to the independent writer connection.
                // Switching DB facade default alone does not rebind models from the main connection.
                $writerActor = \App\Models\Admin::on('d03_service_writer')->findOrFail($cashActor->id);
                $writerOutlet = Outlet::on('d03_service_writer')->findOrFail($historical->id);
                $this->assertTrue($writerActor->usable(), 'Writer actor is not usable.');
                $this->assertTrue($writerActor->hasPermission('shop.cash'), 'Writer lacks shop.cash.');
                $this->assertTrue($writerActor->hasPermission('shops.enter'), 'Writer lacks shops.enter.');
                $this->assertFalse($writerOutlet->status, 'Writer outlet is disabled.');
                $this->assertNull($writerOutlet->archived_at, 'Writer outlet appears archived before commit.');
                $this->assertTrue($writerActor->shops()->whereKey($historical->id)->exists(),
                    'Writer lacks a committed outlet assignment.');
                $this->assertTrue(app(\App\Identity\Access::class)->allows(
                    $writerActor, 'shop.cash', $writerOutlet),
                    'The independent writer must be authorized against its own pre-commit snapshot.');
                try {
                    app(\App\Cash\CashSessionOperations::class)->open($writerActor, $writerOutlet,
                        'd03-overlap-'.$tag, ['opening_cash' => '10.00']);
                    $this->fail('The cash service bypassed the real archive transaction lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($originalDefault);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($originalDefault);
                if (DB::connection('mysql')->transactionLevel() > 0) {
                    DB::connection('mysql')->rollBack();
                }
            }
            $this->assertNotNull($historical->fresh()->archived_at);
            DB::setDefaultConnection('d03_service_writer');
            try {
                foreach ([$historyOpenKey, 'd03-postcommit-'.$tag] as $deniedKey) {
                    try {
                        app(\App\Cash\CashSessionOperations::class)->open($writerActor, $writerOutlet,
                            $deniedKey, ['opening_cash' => '10.00']);
                        $this->fail('Archived outlet accepted an original completed cash key or a fresh open.');
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                        $this->assertSame(403, $exception->getStatusCode());
                    }
                }
            } finally {
                DB::setDefaultConnection($originalDefault);
            }
            $this->assertSame(1, DB::table('cash_sessions')->where('outlet_id', $historical->id)->count());
            $this->assertEquals($historyBefore, DB::table('cash_sessions')->where('public_id', $historicalSessionId)->firstOrFail());
            $this->assertSame(hash('sha256', $historyBefore->closing_snapshot), $historyBefore->snapshot_sha256);
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$cashActor->id)
                ->where('operation', 'cash-sessions.open')
                ->whereIn('key', ['d03-overlap-'.$tag, 'd03-postcommit-'.$tag])->count());
        } finally {
            DB::setDefaultConnection($originalDefault);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            $writer->disconnect();
            DB::purge('d03_service_writer');
            if ($historicalSessionId) {
                DB::table('cash_sessions')->where('public_id', $historicalSessionId)
                    ->where('outlet_id', $historical->id)->delete();
                DB::table('idempotency_requests')->where('actor_scope', \App\Models\Admin::class.':'.$cashActor->id)
                    ->whereIn('key', [$historyOpenKey, 'd03-cash-history-close-'.$tag])->delete();
            }
            DB::table('identity_audit_events')->whereIn('account_id', [$owner->id, $cashActor->id])->delete();
            DB::table('admin_roles')->whereIn('admin_id', [$owner->id, $cashActor->id])->delete();
            DB::table('outlet_admins')->whereIn('admin_id', [$owner->id, $cashActor->id])->delete();
            DB::table('admins')->whereIn('id', [$owner->id, $cashActor->id])->delete();
            DB::table('outlets')->whereIn('id', [$fallback->id, $historical->id])
                ->where('name', 'like', 'D03 service %'.$tag)->delete();
        }
    }
    public function test_isolated_inventory_service_waits_for_archive_lock_and_denies_stale_post_commit_write(): void
    {
        // Actual independent-connection inventory SERVICE vs outlet archive row-lock protocol.
        // Production service still forbids archiving any outlet with a product, so the archival
        // state transition below is deliberately synthetic and must never be used on real data.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $originalDefault = DB::getDefaultConnection();
        $outlet = null;
        $actor = null;
        $product = null;
        $writer = null;
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 inventory lock '.$tag, 'outlet_code' => '069', 'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 inventory lock actor',
                'email' => 'd03-inventory-lock-'.$tag.'@example.invalid',
                'password' => 'not-a-live-password', 'permissions' => ['shops.enter', 'shop.inventory']])->save();
            $actor->shops()->attach($outlet);
            $product = new \App\Models\Product;
            $product->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 zero-stock inventory lock '.$tag, 'category' => 'accessory',
                'price' => '20.00', 'qty' => 0, 'outlet_id' => $outlet->id])->save();
            config(['database.connections.d03_inventory_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_inventory_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                $locked = DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->lockForUpdate()->firstOrFail();
                $this->assertNull($locked->archived_at);
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_inventory_writer');
                $this->assertNull($writer->table('outlets')->where('id', $outlet->id)->value('archived_at'));
                $writerActor = \App\Models\Admin::on('d03_inventory_writer')->findOrFail($actor->id);
                $writerOutlet = Outlet::on('d03_inventory_writer')->findOrFail($outlet->id);
                $this->assertTrue(app(\App\Identity\Access::class)->allows(
                    $writerActor, 'shop.inventory', $writerOutlet));
                try {
                    app(\App\Inventory\InventoryOperations::class)->archive(
                        $writerActor, $writerOutlet, $product->public_id, 'd03-inventory-lock-'.$tag);
                    $this->fail('Inventory service bypassed an independent outlet archive lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($originalDefault);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($originalDefault);
                if (DB::connection('mysql')->transactionLevel() > 0) {
                    DB::connection('mysql')->rollBack();
                }
            }
            $this->assertNotNull($outlet->fresh()->archived_at);
            DB::setDefaultConnection('d03_inventory_writer');
            try {
                app(\App\Inventory\InventoryOperations::class)->archive(
                    $writerActor, $writerOutlet, $product->public_id, 'd03-inventory-after-'.$tag);
                $this->fail('Inventory service accepted an archived-outlet product mutation.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            } finally {
                DB::setDefaultConnection($originalDefault);
            }
            $this->assertFalse((bool) $product->fresh()->isDeleted);
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$actor->id)
                ->where('operation', 'inventory.archive')->where('key', 'like', 'd03-inventory-%'.$tag)->count());
        } finally {
            DB::setDefaultConnection($originalDefault);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_inventory_writer'); }
            if ($actor) {
                DB::table('identity_audit_events')->where('account_id', $actor->id)->delete();
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            if ($product) { DB::table('products')->where('id', $product->id)->delete(); }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 inventory lock '.$tag)->delete(); }
        }
    }

    public function test_isolated_transfer_create_serializes_archived_destination_and_rejects_stale_retry(): void
    {
        // Synthetic two-outlet transfer service race: destination is archived only in the isolated
        // transaction below. No actual product-bearing outlet, shipment or production archive occurs.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $source = null;
        $destination = null;
        $actor = null;
        $writer = null;
        try {
            $source = new Outlet;
            $source->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 transfer source '.$tag, 'outlet_code' => '072',
                'status' => false, 'version' => 1])->save();
            $destination = new Outlet;
            $destination->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 transfer destination '.$tag, 'outlet_code' => '073',
                'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 independent transfer actor',
                'email' => 'd03-transfer-race-'.$tag.'@example.invalid',
                'password' => 'not-a-live-password',
                'permissions' => ['shops.enter', 'shop.transfers.dispatch']])->save();
            $actor->shops()->attach($source);
            $input = ['destination_outlet_id' => $destination->public_id,
                'lines' => [['source_product_id' => (string) Str::uuid(),
                    'destination_product_id' => (string) Str::uuid(), 'quantity' => 1]]];
            config(['database.connections.d03_transfer_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_transfer_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                $locked = DB::connection('mysql')->table('outlets')->where('id', $destination->id)
                    ->lockForUpdate()->firstOrFail();
                $this->assertNull($locked->archived_at);
                DB::connection('mysql')->table('outlets')->where('id', $destination->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_transfer_writer');
                $this->assertNull($writer->table('outlets')->where('id', $destination->id)->value('archived_at'));
                $writerActor = \App\Models\Admin::on('d03_transfer_writer')->findOrFail($actor->id);
                $writerSource = Outlet::on('d03_transfer_writer')->findOrFail($source->id);
                $this->assertTrue(app(\App\Identity\Access::class)->allows(
                    $writerActor, 'shop.transfers.dispatch', $writerSource));
                try {
                    app(\App\Inventory\StockTransferOperations::class)->create(
                        $writerActor, $writerSource, 'd03-transfer-wait-'.$tag, $input);
                    $this->fail('Transfer create bypassed independent destination archive lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($default);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            $this->assertNotNull($destination->fresh()->archived_at);
            DB::setDefaultConnection('d03_transfer_writer');
            try {
                app(\App\Inventory\StockTransferOperations::class)->create(
                    $writerActor, $writerSource, 'd03-transfer-retry-'.$tag, $input);
                $this->fail('Transfer create accepted a post-archive destination.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            } finally {
                DB::setDefaultConnection($default);
            }
            $this->assertSame(0, DB::table('stock_transfers')
                ->where('destination_outlet_id', $destination->id)->count());
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$actor->id)
                ->where('operation', 'transfer.create')->where('key', 'like', 'd03-transfer-%'.$tag)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_transfer_writer'); }
            if ($actor) {
                DB::table('identity_audit_events')->where('account_id', $actor->id)->delete();
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            foreach ([$source, $destination] as $outlet) {
                if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                    ->where('name', 'like', 'D03 transfer %'.$tag)->delete(); }
            }
        }
    }

    public function test_isolated_transfer_receive_serializes_archived_source_and_rejects_stale_retry(): void
    {
        // A synthetic in-transit header suffices to exercise the real receive service's
        // two-outlet gate; receipt/custody execution is intentionally never reached.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $source = null;
        $destination = null;
        $actor = null;
        $transferId = null;
        $writer = null;
        try {
            $source = new Outlet;
            $source->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 receipt source '.$tag, 'outlet_code' => '074',
                'status' => false, 'version' => 1])->save();
            $destination = new Outlet;
            $destination->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 receipt destination '.$tag, 'outlet_code' => '075',
                'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 independent receipt actor',
                'email' => 'd03-receipt-race-'.$tag.'@example.invalid',
                'password' => 'not-a-live-password',
                'permissions' => ['shops.enter', 'shop.transfers.receive']])->save();
            $actor->shops()->attach($destination);
            $transferPublic = (string) Str::uuid();
            $transferId = DB::table('stock_transfers')->insertGetId([
                'public_id' => $transferPublic, 'transfer_number' => 'D03-REC-'.Str::random(14),
                'source_outlet_id' => $source->id, 'destination_outlet_id' => $destination->id,
                'created_by_admin_id' => $actor->id, 'status' => 'in_transit', 'version' => 1]);
            $input = ['transfer_version' => 1, 'lines' => [[
                'line_id' => (string) Str::uuid(), 'receive_quantity' => 1, 'reject_quantity' => 0]]];
            config(['database.connections.d03_transfer_receive_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_transfer_receive_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                $locked = DB::connection('mysql')->table('outlets')->where('id', $source->id)
                    ->lockForUpdate()->firstOrFail();
                $this->assertNull($locked->archived_at);
                DB::connection('mysql')->table('outlets')->where('id', $source->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_transfer_receive_writer');
                $this->assertNull($writer->table('outlets')->where('id', $source->id)->value('archived_at'));
                $writerActor = \App\Models\Admin::on('d03_transfer_receive_writer')->findOrFail($actor->id);
                $writerDestination = Outlet::on('d03_transfer_receive_writer')->findOrFail($destination->id);
                $this->assertTrue(app(\App\Identity\Access::class)->allows(
                    $writerActor, 'shop.transfers.receive', $writerDestination));
                try {
                    app(\App\Inventory\StockTransferOperations::class)->receive(
                        $writerActor, $writerDestination, $transferPublic, 'd03-receipt-wait-'.$tag, $input);
                    $this->fail('Transfer receive bypassed independent source archive lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($default);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            $this->assertNotNull($source->fresh()->archived_at);
            DB::setDefaultConnection('d03_transfer_receive_writer');
            try {
                app(\App\Inventory\StockTransferOperations::class)->receive(
                    $writerActor, $writerDestination, $transferPublic, 'd03-receipt-retry-'.$tag, $input);
                $this->fail('Transfer receive accepted a post-archive source outlet.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            } finally {
                DB::setDefaultConnection($default);
            }
            $this->assertSame('in_transit', DB::table('stock_transfers')->where('id', $transferId)->value('status'));
            $this->assertSame(0, DB::table('stock_transfer_receipts')->where('stock_transfer_id', $transferId)->count());
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$actor->id)
                ->where('operation', 'transfer.receive')->where('key', 'like', 'd03-receipt-%'.$tag)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_transfer_receive_writer'); }
            if ($transferId) { DB::table('stock_transfers')->where('id', $transferId)->delete(); }
            if ($actor) {
                DB::table('identity_audit_events')->where('account_id', $actor->id)->delete();
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            foreach ([$source, $destination] as $outlet) {
                if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                    ->where('name', 'like', 'D03 receipt %'.$tag)->delete(); }
            }
        }
    }

    public function test_isolated_stocktake_start_serializes_archive_lock_and_denies_stale_retry(): void
    {
        // Real stocktake start service on independent MySQL connection; synthetic archive state.
        // The product-bearing outlet remains forbidden by the production archival service.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null;
        $actor = null;
        $product = null;
        $writer = null;
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 stocktake race '.$tag, 'outlet_code' => '076',
                'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 independent stocktake actor',
                'email' => 'd03-stocktake-race-'.$tag.'@example.invalid',
                'password' => 'not-a-live-password',
                'permissions' => ['shops.enter', 'shop.stocktake']])->save();
            $actor->shops()->attach($outlet);
            $product = new \App\Models\Product;
            $product->forceFill(['public_id' => (string) Str::uuid(),
                'name' => 'D03 stocktake fixture '.$tag, 'category' => 'accessory',
                'price' => '30.00', 'qty' => 0, 'outlet_id' => $outlet->id])->save();
            config(['database.connections.d03_stocktake_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_stocktake_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                $locked = DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->lockForUpdate()->firstOrFail();
                $this->assertNull($locked->archived_at);
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_stocktake_writer');
                $this->assertNull($writer->table('outlets')->where('id', $outlet->id)->value('archived_at'));
                $writerActor = \App\Models\Admin::on('d03_stocktake_writer')->findOrFail($actor->id);
                $writerOutlet = Outlet::on('d03_stocktake_writer')->findOrFail($outlet->id);
                $this->assertTrue(app(\App\Identity\Access::class)->allows(
                    $writerActor, 'shop.stocktake', $writerOutlet));
                try {
                    app(\App\Inventory\StocktakeOperations::class)->start(
                        $writerActor, $writerOutlet, 'd03-stocktake-wait-'.$tag, ['kind' => 'full']);
                    $this->fail('Stocktake start bypassed independent outlet archive lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($default);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            $this->assertNotNull($outlet->fresh()->archived_at);
            DB::setDefaultConnection('d03_stocktake_writer');
            try {
                app(\App\Inventory\StocktakeOperations::class)->start(
                    $writerActor, $writerOutlet, 'd03-stocktake-retry-'.$tag, ['kind' => 'full']);
                $this->fail('Stocktake start accepted a post-archive outlet.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            } finally {
                DB::setDefaultConnection($default);
            }
            $this->assertSame(0, DB::table('stocktake_sessions')->where('outlet_id', $outlet->id)->count());
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$actor->id)
                ->where('operation', 'stocktake.start')->where('key', 'like', 'd03-stocktake-%'.$tag)->count());
            $this->assertFalse((bool) $product->fresh()->isDeleted);
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_stocktake_writer'); }
            if ($actor) {
                DB::table('identity_audit_events')->where('account_id', $actor->id)->delete();
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            if ($product) { DB::table('products')->where('id', $product->id)->delete(); }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 stocktake race '.$tag)->delete(); }
        }
    }

    public function test_isolated_sales_and_claim_services_wait_for_archive_and_deny_stale_retries(): void
    {
        // Committed synthetic invoice/sale fixture; the production archive service must still
        // reject this business-bearing outlet. Proves the real services' PRE-CALLBACK gates only.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null; $actor = null; $product = null; $invoiceId = null; $saleId = null; $writer = null;
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => '080',
                'name' => 'D03 sales claim race '.$tag, 'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 synthetic sales claim operator',
                'email' => 'd03-sales-claim-'.$tag.'@example.invalid', 'password' => 'not-a-live-password',
                'permissions' => ['shops.enter', 'shop.sales', 'shop.claims']])->save();
            $actor->shops()->attach($outlet);
            $product = new \App\Models\Product;
            $product->forceFill(['name' => 'D03 retained warranty product', 'outlet_id' => $outlet->id,
                'category' => 'accessory', 'price' => '100.00', 'sale_price' => '100.00', 'qty' => 1,
                'warranty_type' => 'shop_warranty', 'warranty_unit' => 0, 'warranty_duration' => 30])->save();
            $invoiceId = DB::table('invoices')->insertGetId(['outlet_id' => $outlet->id,
                'public_id' => (string) Str::uuid(), 'total_bill' => '100.00',
                'final_bill' => '100.00', 'invoice_number' => 'D03-RACE-'.Str::random(12)]);
            $salePublic = (string) Str::uuid();
            $saleId = DB::table('sales')->insertGetId(['outlet_id' => $outlet->id,
                'public_id' => $salePublic, 'invoice_id' => $invoiceId, 'product_id' => $product->id,
                'sale_date' => now()->toDateString(), 'sale_price' => '100.00', 'quantity' => 1,
                'total_price' => '100.00', 'net_total_price' => '100.00']);
            $beforeSale = DB::table('sales')->where('id', $saleId)->firstOrFail();
            $saleInput = ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]];
            $claimInput = ['sale_id' => $salePublic, 'issue_description' => 'Synthetic warranty intake'];
            config(['database.connections.d03_sale_claim_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_sale_claim_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->lockForUpdate()->firstOrFail();
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_sale_claim_writer');
                $this->assertNull($writer->table('outlets')->where('id', $outlet->id)->value('archived_at'));
                $writerActor = \App\Models\Admin::on('d03_sale_claim_writer')->findOrFail($actor->id);
                $writerOutlet = Outlet::on('d03_sale_claim_writer')->findOrFail($outlet->id);
                foreach (['shop.sales', 'shop.claims'] as $permission) {
                    $this->assertTrue(app(\App\Identity\Access::class)->allows(
                        $writerActor, $permission, $writerOutlet));
                }
                foreach (['sale', 'claim'] as $kind) {
                    try {
                        if ($kind === 'sale') {
                            app(\App\Sales\SalesOperations::class)->sell($writerActor, $writerOutlet,
                                'd03-sale-wait-'.$tag, $saleInput);
                        } else {
                            app(\App\Warranty\ClaimOperations::class)->open($writerActor, $writerOutlet,
                                'd03-claim-wait-'.$tag, $claimInput);
                        }
                        $this->fail('A '.$kind.' write bypassed the independent outlet archive lock.');
                    } catch (QueryException $exception) {
                        $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                    }
                }
                DB::setDefaultConnection($default);
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            $this->assertNotNull($outlet->fresh()->archived_at);
            DB::setDefaultConnection('d03_sale_claim_writer');
            try {
                foreach (['sale', 'claim'] as $kind) {
                    try {
                        if ($kind === 'sale') {
                            app(\App\Sales\SalesOperations::class)->sell($writerActor, $writerOutlet,
                                'd03-sale-retry-'.$tag, $saleInput);
                        } else {
                            app(\App\Warranty\ClaimOperations::class)->open($writerActor, $writerOutlet,
                                'd03-claim-retry-'.$tag, $claimInput);
                        }
                        $this->fail('A '.$kind.' service accepted a post-archive retry.');
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                        $this->assertSame(403, $exception->getStatusCode());
                    }
                }
            } finally {
                DB::setDefaultConnection($default);
            }
            $this->assertEquals($beforeSale, DB::table('sales')->where('id', $saleId)->firstOrFail());
            $this->assertSame(1, DB::table('invoices')->where('id', $invoiceId)->count());
            $this->assertSame(0, DB::table('claims')->where('outlet_id', $outlet->id)->count());
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$actor->id)
                ->where('key', 'like', 'd03-%'.$tag)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_sale_claim_writer'); }
            if ($saleId) { DB::table('sales')->where('id', $saleId)->delete(); }
            if ($invoiceId) { DB::table('invoices')->where('id', $invoiceId)->delete(); }
            if ($product) { DB::table('products')->where('id', $product->id)->delete(); }
            if ($actor) {
                DB::table('identity_audit_events')->where('account_id', $actor->id)->delete();
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 sales claim race '.$tag)->delete(); }
        }
    }

    public function test_isolated_payment_service_serializes_archive_lock_and_rejects_stale_destination_retry(): void
    {
        // Test-only committed fixture. This proves the shared payment mutation gate,
        // not a live card charge, provider settlement or actual payment-history archive.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null; $actor = null; $writer = null;
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => '081',
                'name' => 'D03 payment race '.$tag, 'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 synthetic payment operator',
                'email' => 'd03-payment-race-'.$tag.'@example.invalid',
                'password' => 'not-a-live-password',
                'permissions' => ['shops.enter', 'config.payments.manage']])->save();
            $actor->shops()->attach($outlet);
            $input = ['method' => 'card', 'display_name' => 'Isolated synthetic destination'];
            config(['database.connections.d03_payment_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_payment_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->lockForUpdate()->firstOrFail();
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_payment_writer');
                $this->assertNull($writer->table('outlets')->where('id', $outlet->id)->value('archived_at'));
                $writerActor = \App\Models\Admin::on('d03_payment_writer')->findOrFail($actor->id);
                $writerOutlet = Outlet::on('d03_payment_writer')->findOrFail($outlet->id);
                $this->assertTrue(app(\App\Identity\Access::class)->allows(
                    $writerActor, 'config.payments.manage', $writerOutlet));
                try {
                    app(\App\Payments\PosPaymentOperations::class)->createDestination(
                        $writerActor, $writerOutlet, 'd03-payment-wait-'.$tag, $input);
                    $this->fail('Payment configuration bypassed the independent archive lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($default);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            $this->assertNotNull($outlet->fresh()->archived_at);
            DB::setDefaultConnection('d03_payment_writer');
            try {
                app(\App\Payments\PosPaymentOperations::class)->createDestination(
                    $writerActor, $writerOutlet, 'd03-payment-retry-'.$tag, $input);
                $this->fail('Payment service accepted a post-archive destination.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            } finally {
                DB::setDefaultConnection($default);
            }
            $this->assertSame(0, DB::table('pos_payment_destinations')->where('outlet_id', $outlet->id)->count());
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$actor->id)
                ->where('operation', 'pos-payments.destination.create')
                ->where('key', 'like', 'd03-payment-%'.$tag)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_payment_writer'); }
            if ($actor) {
                DB::table('identity_audit_events')->where('account_id', $actor->id)->delete();
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 payment race '.$tag)->delete(); }
        }
    }

    public function test_isolated_procurement_and_repair_services_share_archive_lock(): void
    {
        // Committed test-only fixture; neither real procurement nor real repair outlet archival is enabled.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null; $actor = null; $writer = null;
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => '082',
                'name' => 'D03 supplier repair race '.$tag, 'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 synthetic supplier repair actor',
                'email' => 'd03-supplier-repair-'.$tag.'@example.invalid', 'password' => 'not-a-live-password',
                'permissions' => ['shops.enter', 'shop.procurement', 'shop.repairs']])->save();
            $actor->shops()->attach($outlet);
            $supplierInput = ['supplier_code' => 'D03-RACE', 'name' => 'Synthetic Supplier'];
            $repairInput = ['enabled' => true];
            config(['database.connections.d03_procurement_repair_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_procurement_repair_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->lockForUpdate()->firstOrFail();
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_procurement_repair_writer');
                $this->assertNull($writer->table('outlets')->where('id', $outlet->id)->value('archived_at'));
                $writerActor = \App\Models\Admin::on('d03_procurement_repair_writer')->findOrFail($actor->id);
                $writerOutlet = Outlet::on('d03_procurement_repair_writer')->findOrFail($outlet->id);
                foreach (['shop.procurement', 'shop.repairs'] as $permission) {
                    $this->assertTrue(app(\App\Identity\Access::class)->allows(
                        $writerActor, $permission, $writerOutlet));
                }
                foreach (['supplier', 'repair'] as $kind) {
                    try {
                        if ($kind === 'supplier') {
                            app(\App\Procurement\SupplierProcurement::class)->createSupplier(
                                $writerActor, $writerOutlet, 'd03-supplier-wait-'.$tag, $supplierInput);
                        } else {
                            app(\App\Repairs\PaidRepairOperations::class)->configure(
                                $writerActor, $writerOutlet, 'd03-repair-wait-'.$tag, $repairInput);
                        }
                        $this->fail('The '.$kind.' service bypassed the archive row lock.');
                    } catch (QueryException $exception) {
                        $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                    }
                }
                DB::setDefaultConnection($default);
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            $this->assertNotNull($outlet->fresh()->archived_at);
            DB::setDefaultConnection('d03_procurement_repair_writer');
            try {
                foreach (['supplier', 'repair'] as $kind) {
                    try {
                        if ($kind === 'supplier') {
                            app(\App\Procurement\SupplierProcurement::class)->createSupplier(
                                $writerActor, $writerOutlet, 'd03-supplier-retry-'.$tag, $supplierInput);
                        } else {
                            app(\App\Repairs\PaidRepairOperations::class)->configure(
                                $writerActor, $writerOutlet, 'd03-repair-retry-'.$tag, $repairInput);
                        }
                        $this->fail('Archived outlet accepted '.$kind.' mutation.');
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                        $this->assertSame(403, $exception->getStatusCode());
                    }
                }
            } finally {
                DB::setDefaultConnection($default);
            }
            $this->assertSame(0, DB::table('suppliers')->where('outlet_id', $outlet->id)->count());
            $this->assertSame(0, DB::table('repair_settings')->where('outlet_id', $outlet->id)->count());
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$actor->id)
                ->where('key', 'like', 'd03-%'.$tag)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_procurement_repair_writer'); }
            if ($actor) {
                DB::table('identity_audit_events')->where('account_id', $actor->id)->delete();
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 supplier repair race '.$tag)->delete(); }
        }
    }

    public function test_isolated_trade_in_service_shares_archive_lock(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null; $actor = null; $product = null; $writer = null;
        $input = ['seller_name' => 'Synthetic Seller', 'seller_cnic' => '42101-1234567-1',
            'seller_phone' => '03001234567', 'seller_address' => 'Synthetic private address',
            'device_serial' => 'D03-RACE', 'imeis' => ['352099001761466', '352099001761474'],
            'condition' => 'Used - inspected', 'diagnostics' => ['display' => 'Pass'],
            'valuation_amount' => '100.00', 'settlement_mode' => 'purchase', 'invoice_id' => null];
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => '083',
                'name' => 'D03 trade-in race '.$tag, 'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 synthetic trade-in actor',
                'email' => 'd03-trade-in-'.$tag.'@example.invalid', 'password' => 'not-a-live-password',
                'permissions' => ['shops.enter', 'shop.trade-in']])->save();
            $actor->shops()->attach($outlet);
            $product = new \App\Models\Product;
            $product->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'D03 trade-in product '.$tag,
                'category' => 'mobile_phone', 'price' => '100.00', 'sale_price' => '100.00', 'qty' => 0,
                'track_imei' => true, 'sim_configuration' => 'dual_physical', 'outlet_id' => $outlet->id])->save();
            config(['database.connections.d03_trade_in_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_trade_in_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->lockForUpdate()->firstOrFail();
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_trade_in_writer');
                $writerActor = \App\Models\Admin::on('d03_trade_in_writer')->findOrFail($actor->id);
                $writerOutlet = Outlet::on('d03_trade_in_writer')->findOrFail($outlet->id);
                $writerProduct = \App\Models\Product::on('d03_trade_in_writer')->findOrFail($product->id);
                $this->assertTrue(app(\App\Identity\Access::class)->allows($writerActor, 'shop.trade-in', $writerOutlet));
                try {
                    app(\App\TradeIn\TradeInOperations::class)->create(
                        $writerActor, $writerOutlet, $writerProduct->public_id, 'd03-trade-in-wait-'.$tag, $input);
                    $this->fail('Trade-in service bypassed the archive row lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                }
                DB::setDefaultConnection($default);
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            DB::setDefaultConnection('d03_trade_in_writer');
            try {
                app(\App\TradeIn\TradeInOperations::class)->create(
                    $writerActor, $writerOutlet, $writerProduct->public_id, 'd03-trade-in-retry-'.$tag, $input);
                $this->fail('Archived outlet accepted a trade-in mutation.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            } finally {
                DB::setDefaultConnection($default);
            }
            $this->assertSame(0, DB::table('trade_ins')->where('outlet_id', $outlet->id)->count());
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$actor->id)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_trade_in_writer'); }
            if ($product) { DB::table('products')->where('id', $product->id)->delete(); }
            if ($actor) {
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 trade-in race '.$tag)->delete(); }
        }
    }

    public function test_isolated_website_checkout_shares_archive_lock(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null; $product = null; $writer = null;
        $scope = 'guest:'.hash('sha256', 'd03-website-'.$tag);
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => '084',
                'name' => 'D03 website race '.$tag, 'status' => false, 'version' => 1])->save();
            $product = new \App\Models\Product;
            $product->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'D03 website product '.$tag,
                'category' => 'accessory', 'price' => '100.00', 'sale_price' => '100.00', 'qty' => 1,
                'track_imei' => false, 'outlet_id' => $outlet->id])->save();
            $input = ['customer_name' => 'Synthetic Guest', 'customer_mobile' => '03001234567',
                'gateway' => 'cod', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]];
            config(['database.connections.d03_website_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_website_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->lockForUpdate()->firstOrFail();
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_website_writer');
                $this->assertNull($writer->table('outlets')->where('id', $outlet->id)->value('archived_at'));
                try {
                    app(\App\Commerce\OrderTransactions::class)->checkout(
                        $scope, null, 'd03-website-wait-'.$tag, $input);
                    $this->fail('Website checkout bypassed the independent archive lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($default);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            DB::setDefaultConnection('d03_website_writer');
            try {
                app(\App\Commerce\OrderTransactions::class)->checkout(
                    $scope, null, 'd03-website-retry-'.$tag, $input);
                $this->fail('Archived outlet accepted a Website checkout.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            } finally {
                DB::setDefaultConnection($default);
            }
            $this->assertSame(0, DB::table('orders')->where('owner_scope_hash', hash('sha256', $scope))->count());
            $this->assertSame(0, DB::table('idempotency_requests')->where('actor_scope', $scope)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_website_writer'); }
            if ($product) { DB::table('products')->where('id', $product->id)->delete(); }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 website race '.$tag)->delete(); }
        }
    }

    public function test_isolated_website_callback_waits_for_archive_then_preserves_reconciliation(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null; $orderId = null; $writer = null;
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => '085',
                'name' => 'D03 callback race '.$tag, 'status' => false, 'version' => 1])->save();
            $orderId = DB::table('orders')->insertGetId(['order_number' => 'D03-CB-'.$tag, 'order_type' => 'commerce',
                'status' => 'pending', 'fulfillment_status' => 'pending', 'customer_name' => 'Synthetic',
                'customer_mobile' => '03001234567', 'subtotal' => '100.00', 'total' => '100.00', 'currency' => 'PKR',
                'payment_status' => 'unpaid', 'owner_scope_hash' => hash('sha256', 'guest:'.$tag),
                'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
            $paymentPublic = (string) Str::uuid();
            $paymentId = DB::table('payments')->insertGetId(['order_id' => $orderId, 'gateway' => 'jazzcash',
                'status' => 'pending', 'amount' => '100.00', 'currency' => 'PKR', 'public_id' => $paymentPublic,
                'merchant' => 'd03-merchant', 'mode' => 'test', 'attempt_key' => 'checkout-1',
                'intent_hash' => str_repeat('a', 64), 'gateway_order_reference' => 'D03-GW-'.$tag,
                'initiated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('reservations')->insert(['order_id' => $orderId, 'attempt' => 1, 'outlet_id' => $outlet->id,
                'website_order_number' => 'D03-CB-'.$tag, 'reservation_reference' => (string) Str::uuid(),
                'state' => 'active', 'currency' => 'PKR', 'customer_name' => 'Synthetic', 'customer_mobile' => '03001234567',
                'total_amount' => '100.00', 'reservation_expires_at' => now()->addMinutes(20), 'gateway' => 'jazzcash',
                'website_payment_id' => (string) $paymentId, 'contract_hash' => str_repeat('b', 64),
                'created_at' => now(), 'updated_at' => now()]);
            $event = ['event_id' => 'D03-EVT-'.$tag, 'transaction_reference' => 'D03-TX-'.$tag,
                'order_reference' => 'D03-GW-'.$tag, 'amount' => '100.00', 'currency' => 'PKR', 'status' => 'paid',
                'payload_hash' => hash('sha256', $tag)];
            config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'd03-merchant', 'mode' => 'test']);
            $providers = new \App\Commerce\PaymentProviders;
            $providers->register('jazzcash', new class($event) implements \App\Commerce\PaymentProvider {
                public function __construct(private array $event) {}
                public function initiate(array $intent): array { return []; }
                public function verify(array $payload): array { return $this->event; }
            });
            $this->app->instance(\App\Commerce\PaymentProviders::class, $providers);
            config(['database.connections.d03_callback_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_callback_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)->lockForUpdate()->firstOrFail();
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_callback_writer');
                try {
                    app(\App\Commerce\OrderTransactions::class)->callback('jazzcash', ['synthetic' => true]);
                    $this->fail('Provider callback bypassed the independent archive lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                } finally {
                    DB::setDefaultConnection($default);
                }
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            DB::setDefaultConnection('d03_callback_writer');
            $result = app(\App\Commerce\OrderTransactions::class)->callback('jazzcash', ['synthetic' => true]);
            DB::setDefaultConnection($default);
            $this->assertSame('paid_reconciliation', $result['payment_status']);
            $this->assertTrue($result['reconciliation_required']);
            $this->assertSame(1, DB::table('payment_receipts')->where('payment_id', $paymentId)->count());
            $this->assertSame(0, DB::table('invoices')->where('order_id', $orderId)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_callback_writer'); }
            if ($orderId) {
                DB::table('payment_receipts')->whereIn('payment_id', DB::table('payments')->where('order_id', $orderId)->pluck('id'))->delete();
                DB::table('reservations')->where('order_id', $orderId)->delete();
                DB::table('payments')->where('order_id', $orderId)->delete();
                DB::table('orders')->where('id', $orderId)->delete();
            }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 callback race '.$tag)->delete(); }
        }
    }

    public function test_isolated_cod_and_refund_services_share_archive_lock(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403);
        $tag = (string) Str::uuid();
        $default = DB::getDefaultConnection();
        $outlet = null; $actor = null; $orderId = null; $returnId = null; $writer = null;
        try {
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => '086',
                'name' => 'D03 COD refund race '.$tag, 'status' => false, 'version' => 1])->save();
            $actor = new \App\Models\Admin;
            $actor->forceFill(['name' => 'D03 COD refund actor', 'email' => 'd03-cod-refund-'.$tag.'@example.invalid',
                'password' => 'not-a-live-password', 'permissions' => ['shops.enter', 'shop.sales']])->save();
            $actor->shops()->attach($outlet);
            $orderPublic = (string) Str::uuid();
            $orderId = DB::table('orders')->insertGetId(['order_number' => 'D03-MONEY-'.$tag, 'order_type' => 'commerce',
                'status' => 'pending', 'fulfillment_status' => 'pending', 'customer_name' => 'Synthetic',
                'customer_mobile' => '03001234567', 'subtotal' => '100.00', 'total' => '100.00', 'currency' => 'PKR',
                'payment_status' => 'unpaid', 'owner_scope_hash' => hash('sha256', 'guest:'.$tag),
                'public_id' => $orderPublic, 'created_at' => now(), 'updated_at' => now()]);
            $paymentPublic = (string) Str::uuid();
            $paymentId = DB::table('payments')->insertGetId(['order_id' => $orderId, 'gateway' => 'cod',
                'status' => 'pending_collection', 'amount' => '100.00', 'currency' => 'PKR', 'public_id' => $paymentPublic,
                'merchant' => 'cash-on-delivery', 'mode' => 'manual', 'attempt_key' => 'checkout-1',
                'intent_hash' => str_repeat('a', 64), 'initiated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('reservations')->insert(['order_id' => $orderId, 'attempt' => 1, 'outlet_id' => $outlet->id,
                'website_order_number' => 'D03-MONEY-'.$tag, 'reservation_reference' => (string) Str::uuid(),
                'state' => 'held_cod', 'currency' => 'PKR', 'customer_name' => 'Synthetic', 'customer_mobile' => '03001234567',
                'total_amount' => '100.00', 'gateway' => 'cod', 'website_payment_id' => (string) $paymentId,
                'contract_hash' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now()]);
            $invoiceId = DB::table('invoices')->insertGetId(['outlet_id' => $outlet->id, 'order_id' => $orderId,
                'total_bill' => '100.00', 'final_bill' => '100.00', 'invoice_number' => 'D03-INV-'.$tag,
                'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
            $returnPublic = (string) Str::uuid();
            $returnId = DB::table('returns')->insertGetId(['invoice_id' => $invoiceId, 'order_id' => $orderId,
                'actor_type' => \App\Models\Admin::class, 'actor_id' => $actor->id, 'reason' => 'Synthetic return',
                'status' => 'accepted', 'idempotency_key' => 'd03-return-'.$tag, 'public_id' => $returnPublic,
                'created_at' => now(), 'updated_at' => now()]);
            config(['database.connections.d03_money_writer' => config('database.connections.mysql')]);
            $writer = DB::connection('d03_money_writer');
            $writer->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::connection('mysql')->beginTransaction();
            try {
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)->lockForUpdate()->firstOrFail();
                DB::connection('mysql')->table('outlets')->where('id', $outlet->id)->update(['archived_at' => now(), 'version' => 2]);
                DB::setDefaultConnection('d03_money_writer');
                $writerActor = \App\Models\Admin::on('d03_money_writer')->findOrFail($actor->id);
                $writerOutlet = Outlet::on('d03_money_writer')->findOrFail($outlet->id);
                foreach (['cod', 'refund'] as $kind) {
                    try {
                        if ($kind === 'cod') {
                            app(\App\Commerce\OrderTransactions::class)->collectCod($writerActor, $writerOutlet,
                                $orderPublic, 'd03-cod-wait-'.$tag, '100.00', 'D03-COD-'.$tag);
                        } else {
                            app(\App\Commerce\OrderTransactions::class)->manualRefund($writerActor, $returnPublic,
                                $paymentPublic, 'd03-refund-wait-'.$tag, '1.00', str_repeat('c', 64));
                        }
                        $this->fail($kind.' bypassed the independent archive lock.');
                    } catch (QueryException $exception) {
                        $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                    }
                }
                DB::setDefaultConnection($default);
                DB::connection('mysql')->commit();
            } finally {
                DB::setDefaultConnection($default);
                if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            }
            DB::setDefaultConnection('d03_money_writer');
            foreach (['cod', 'refund'] as $kind) {
                try {
                    if ($kind === 'cod') {
                        app(\App\Commerce\OrderTransactions::class)->collectCod($writerActor, $writerOutlet,
                            $orderPublic, 'd03-cod-retry-'.$tag, '100.00', 'D03-COD-2-'.$tag);
                    } else {
                        app(\App\Commerce\OrderTransactions::class)->manualRefund($writerActor, $returnPublic,
                            $paymentPublic, 'd03-refund-retry-'.$tag, '1.00', str_repeat('d', 64));
                    }
                    $this->fail('Archived outlet accepted '.$kind.'.');
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
            }
            DB::setDefaultConnection($default);
            $this->assertSame(0, DB::table('payment_receipts')->where('payment_id', $paymentId)->count());
            $this->assertSame(0, DB::table('refunds')->where('payment_id', $paymentId)->count());
        } finally {
            DB::setDefaultConnection($default);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            if ($writer) { $writer->disconnect(); DB::purge('d03_money_writer'); }
            if ($returnId) { DB::table('returns')->where('id', $returnId)->delete(); }
            if ($orderId) {
                DB::table('reservations')->where('order_id', $orderId)->delete();
                DB::table('invoices')->where('order_id', $orderId)->delete();
                DB::table('payments')->where('order_id', $orderId)->delete();
                DB::table('orders')->where('id', $orderId)->delete();
            }
            if ($actor) {
                DB::table('outlet_admins')->where('admin_id', $actor->id)->delete();
                DB::table('admins')->where('id', $actor->id)->delete();
            }
            if ($outlet) { DB::table('outlets')->where('id', $outlet->id)
                ->where('name', 'D03 COD refund race '.$tag)->delete(); }
        }
    }

}
