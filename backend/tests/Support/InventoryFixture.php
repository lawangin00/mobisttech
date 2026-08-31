<?php

namespace Tests\Support;

use App\Catalog\ProductDefinitions;
use App\Inventory\InventoryOperations;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\SuperAdmin;
use App\Services\PosInventoryMasterData;
use Database\Seeders\PosMasterDataSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait InventoryFixture
{
    private Outlet $outlet;

    private SuperAdmin $actor;

    private function inventoryFixture(): void
    {
        $this->seed(PosMasterDataSeeder::class);
        $this->outlet = new Outlet;
        $this->outlet->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Synthetic inventory', 'outlet_code' => '024'])->save();
        $this->actor = new SuperAdmin;
        $this->actor->forceFill(['name' => 'Synthetic inventory operator', 'email' => 'stock@example.invalid', 'password' => 'SyntheticPass123!'])->save();
    }

    private function product(bool $tracked = false): Product
    {
        $brand = PosMasterDataOption::where('list_key', 'product_brand')->first() ?? app(PosInventoryMasterData::class)->create('product_brand', ['label' => 'Synthetic brand']);

        return app(ProductDefinitions::class)->save($this->actor, $this->outlet, ['name' => 'Stock '.Str::uuid(), 'category' => $tracked ? 'mobile_phone' : 'accessory',
            'brand_master_data_id' => $brand->id, 'model' => 'Synthetic model', 'purchase_price' => '100.01', 'sale_price' => '200.02', 'track_imei' => $tracked,
            'sim_configuration' => 'dual_physical', 'warranty_type' => 'no_warranty']);
    }

    private function acquire(Product $product, int $quantity = 1, ?string $key = null): array
    {
        return app(InventoryOperations::class)->acquire($this->actor, $this->outlet, $product->public_id, $key ?? (string) Str::uuid(), $this->acquisitionInput($quantity));
    }

    private function acquisitionInput(int $quantity): array
    {
        return ['quantity' => $quantity, 'unit_purchase_price' => '123.45', 'source_type_master_data_id' => PosMasterDataOption::where('list_key', 'acquisition_source_type')->where('code', 'supplier')->value('id'),
            'business_name' => 'Synthetic supplier', 'seller_phone' => '0300-0000000', 'seller_address' => 'Synthetic address', 'reason' => 'Synthetic receipt'];
    }

    private function imeis(Product $product, ?StockUnit $unit = null, array $values = [1 => 'Synthetic-IMEI-1', 2 => 'Synthetic-IMEI-2']): array
    {
        $unit ??= StockUnit::where('product_id', $product->id)->firstOrFail();

        return app(InventoryOperations::class)->imeis($this->actor, $this->outlet, $product->public_id, (string) Str::uuid(),
            ['unit_id' => $unit->public_id, 'version' => $unit->version, 'imeis' => $values]);
    }

    private function reservation(Product $product, int $quantity = 1, string $variant = 'standard'): array
    {
        $number = 'MT24-'.Str::uuid();
        $order = DB::table('orders')->insertGetId(['order_number' => $number, 'order_type' => 'mobile', 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000000', 'public_id' => (string) Str::uuid()]);
        $item = DB::table('order_items')->insertGetId(['order_id' => $order, 'item_type' => 'mobile', 'title' => 'Synthetic item', 'quantity' => $quantity, 'product_id' => $product->id, 'outlet_id' => $product->outlet_id]);
        $reservation = DB::table('reservations')->insertGetId(['order_id' => $order, 'outlet_id' => $product->outlet_id, 'website_order_number' => $number,
            'reservation_reference' => (string) Str::uuid(), 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000000', 'reservation_expires_at' => now()->addMinutes(20)]);
        $line = DB::table('reservation_lines')->insertGetId(['reservation_id' => $reservation, 'order_item_id' => $item, 'product_id' => $product->id, 'outlet_id' => $product->outlet_id,
            'website_order_item_id' => (string) $item, 'product_external_id' => (string) $product->id, 'outlet_external_id' => '024', 'variant_key' => $variant,
            'quantity' => $quantity, 'unit_price' => '200.02', 'line_total' => bcmul('200.02', (string) $quantity, 2)]);

        return ['order' => $order, 'reservation' => $reservation, 'line' => $line];
    }

    private function sale(Product $product, int $quantity = 1, ?int $order = null): int
    {
        $invoice = DB::table('invoices')->insertGetId(['outlet_id' => $product->outlet_id, 'order_id' => $order, 'total_bill' => '200.02', 'final_bill' => '200.02', 'public_id' => (string) Str::uuid()]);

        return DB::table('sales')->insertGetId(['outlet_id' => $product->outlet_id, 'product_id' => $product->id, 'invoice_id' => $invoice,
            'sale_date' => '2026-08-31', 'sale_price' => '200.02', 'total_price' => bcmul('200.02', (string) $quantity, 2), 'quantity' => $quantity]);
    }

    private function reject(callable $callback): void
    {
        try {
            $callback();
        } catch (\LogicException|ValidationException|QueryException|RecordNotFoundException|ModelNotFoundException|HttpException $error) {
            $this->assertNotEmpty($error::class);

            return;
        }
        $this->fail('Invalid stock mutation succeeded.');
    }
}
