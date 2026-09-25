<?php

namespace Database\Seeders;

use App\Infrastructure\PrivateObjects;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class H01InventoryOperatorBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_H01_INVENTORY_OPERATOR_E2E_ENABLED') === '1', 403);
        $receipt = base_path('../.local/h01-inventory-operator-browser-receipt.json');
        $action = getenv('MT75_H01_INVENTORY_OPERATOR_FIXTURE_ACTION');
        abort_unless(in_array($action, ['seed', 'cleanup'], true), 403);
        $owner = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->firstOrFail();
        $outlet = DB::table('outlets')->where('outlet_code', 'E41')->where('name', 'E2E Sales Outlet')->firstOrFail();
        if ($action === 'seed') {
            abort_unless(! is_file($receipt) && DB::table('products')->where('name', 'H01 isolated evidence accessory')->doesntExist(), 409);
            $ids = DB::transaction(function () use ($outlet) {
                $id = DB::table('products')->insertGetId([
                    'name' => 'H01 isolated evidence accessory', 'price' => '100.00',
                    'outlet_id' => $outlet->id, 'qty' => 0, 'sold_qty' => 1,
                    'category' => 'accessory', 'purchase_price' => '100.00', 'sale_price' => '125.00',
                    'warranty_type' => 'no_warranty', 'product_code' => 'H01-'.strtoupper(Str::random(10)),
                    'public_id' => (string) Str::uuid(), 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $acquisition = DB::table('stock_acquisitions')->insertGetId([
                    'product_id' => $id, 'outlet_id' => $outlet->id, 'source_type' => 'supplier',
                    'business_name' => 'H01 Synthetic Fixture', 'quantity' => 1, 'unit_purchase_price' => '100.00',
                    'seller_phone' => '03000000000', 'seller_address' => 'Synthetic isolated browser fixture',
                    'acquired_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ([['restock', 1, 0, 1], ['correction_out', -1, 1, 0]] as [$type, $delta, $before, $after]) {
                    DB::table('stock_movements')->insert(['product_id' => $id, 'outlet_id' => $outlet->id,
                        'type' => $type, 'quantity_change' => $delta, 'stock_before' => $before, 'stock_after' => $after,
                        'reason' => 'H01 owned synthetic test fixture', 'created_at' => now(), 'updated_at' => now()]);
                }

                return ['product' => $id, 'acquisition' => $acquisition];
            });
            $row = DB::table('products')->where('id', $ids['product'])->firstOrFail();
            file_put_contents($receipt, json_encode([...$ids, 'public_id' => $row->public_id, 'owner_id' => $owner->id, 'outlet_id' => $outlet->id], JSON_THROW_ON_ERROR));
            $this->command?->info('H01 exact-owned zero-stock product and synthetic acquisition fixture seeded.');

            return;
        }
        abort_unless(is_file($receipt), 409);
        $ids = json_decode(file_get_contents($receipt), true, flags: JSON_THROW_ON_ERROR);
        abort_unless((int) $ids['owner_id'] === $owner->id && (int) $ids['outlet_id'] === $outlet->id, 409);
        $product = DB::table('products')->where('id', $ids['product'])->firstOrFail();
        $acquisition = DB::table('stock_acquisitions')->where('id', $ids['acquisition'])->firstOrFail();
        abort_unless($product->name === 'H01 isolated evidence accessory' && $product->public_id === $ids['public_id']
            && (int) $product->outlet_id === $outlet->id && (int) $acquisition->product_id === $product->id
            && $acquisition->business_name === 'H01 Synthetic Fixture', 409);
        if ($acquisition->cnic_front_path) {
            abort_unless(preg_match('/\Aacquisitions\/[0-9a-f-]{36}\.png\z/', $acquisition->cnic_front_path) === 1
                && app(PrivateObjects::class)->exists($acquisition->cnic_front_path), 409);
            app(PrivateObjects::class)->delete($acquisition->cnic_front_path);
        }
        abort_unless($acquisition->cnic_back_path === null, 409);
        DB::transaction(function () use ($ids, $owner, $outlet) {
            DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $owner->id)->where('outlet_id', $outlet->id)
                ->whereIn('reference', ['product:'.$ids['public_id'], 'acquisition:'.$ids['acquisition'], 'acquisition:'.$ids['acquisition'].':front'])
                ->whereIn('action', ['inventory_archive', 'acquisition_evidence_attached', 'acquisition_evidence_read'])->delete();
            DB::table('idempotency_requests')->where('resource_type', 'product')->where('resource_id', $ids['product'])
                ->where('operation', 'inventory.archive')->where('actor_scope', 'App\Models\Admin:'.$owner->id)->delete();
            DB::table('stock_movements')->where('product_id', $ids['product'])->delete();
            DB::table('stock_acquisitions')->where('id', $ids['acquisition'])->delete();
            DB::table('products')->where('id', $ids['product'])->delete();
        });
        unlink($receipt);
        $this->command?->info('H01 exact-owned inventory fixture, private file and audit cleaned.');
    }
}
