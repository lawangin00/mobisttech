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
        try {
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
                app(\App\Cash\CashSessionOperations::class)->open($writerActor, $writerOutlet,
                    'd03-postcommit-'.$tag, ['opening_cash' => '10.00']);
                $this->fail('The cash service opened a session after the archive committed.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            } finally {
                DB::setDefaultConnection($originalDefault);
            }
            $this->assertSame(0, DB::table('cash_sessions')->where('outlet_id', $historical->id)->count());
            $this->assertSame(0, DB::table('idempotency_requests')
                ->where('actor_scope', \App\Models\Admin::class.':'.$cashActor->id)
                ->where('operation', 'cash-sessions.open')->where('key', 'like', 'd03-%'.$tag)->count());
        } finally {
            DB::setDefaultConnection($originalDefault);
            if (DB::connection('mysql')->transactionLevel() > 0) { DB::connection('mysql')->rollBack(); }
            $writer->disconnect();
            DB::purge('d03_service_writer');
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

}
