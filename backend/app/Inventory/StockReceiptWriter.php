<?php

namespace App\Inventory;

use App\Catalog\ProductDefinitions;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Services\PosInventoryMasterData;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Internal acquisition writer. The owning service supplies authorization, validation, locking and idempotency. */
final class StockReceiptWriter
{
    public function __construct(private StockLedger $stock) {}

    public function receive(Product $product, PosMasterDataOption $source, int $quantity, string $landedUnitCost, array $party, string $reason): array
    {
        if (DB::transactionLevel() < 1 || $quantity < 1 || $product->outlet_id < 1) {
            throw new LogicException('A stock receipt requires an owning transaction and positive quantity.');
        }
        $acquisition = DB::table('stock_acquisitions')->insertGetId([
            'product_id' => $product->id, 'outlet_id' => $product->outlet_id,
            'source_type' => $source->code, 'source_type_master_data_id' => $source->id,
            'business_name' => $party['business_name'] ?? null, 'seller_name' => $party['seller_name'] ?? null,
            'seller_cnic' => $party['seller_cnic'] ?? null, 'seller_phone' => $party['seller_phone'],
            'seller_address' => $party['seller_address'], 'quantity' => $quantity,
            'unit_purchase_price' => $landedUnitCost, 'acquired_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(PosInventoryMasterData::class)->syncUsage(null, $source, 'stock_acquisition', $acquisition, 'source_type');
        $this->blankUnits($product, $quantity, $landedUnitCost, $acquisition);
        $product->purchase_price = $landedUnitCost;
        $movement = $this->stock->movement($product, 'restock', $quantity, 'stock_acquisition', $acquisition, $reason);

        return ['acquisition_id' => $acquisition, 'movement_id' => $movement];
    }

    public function correctionUnits(Product $product, int $quantity, string $cost): void
    {
        if (DB::transactionLevel() < 1 || $quantity < 1) {
            throw new LogicException('Stock correction units require an owning transaction and positive quantity.');
        }
        $this->blankUnits($product, $quantity, $cost, null);
    }

    private function blankUnits(Product $product, int $quantity, string $cost, ?int $acquisition): void
    {
        if (! $product->track_imei) {
            return;
        }
        $number = (int) StockUnit::where('product_id', $product->id)->orderByDesc('unit_no')->lockForUpdate()->value('unit_no');
        $master = app(PosInventoryMasterData::class);
        for ($i = 0; $i < $quantity; $i++) {
            $unit = new StockUnit;
            $unit->forceFill(['product_id' => $product->id, 'unit_no' => ++$number, 'stock_acquisition_id' => $acquisition,
                'purchase_price' => $cost, 'status' => 'in_stock']);
            foreach (ProductDefinitions::UNIT_OPTIONS as $field => [$list, $raw]) {
                if ($raw === 'color') {
                    continue;
                }
                $option = $master->optionByCode($list, 'unknown');
                $unit->$field = $option?->id;
                $unit->$raw = 'unknown';
            }
            $unit->save();
            foreach (ProductDefinitions::UNIT_OPTIONS as $field => [$list, $raw]) {
                if ($unit->$field) {
                    $master->syncUsage(null, PosMasterDataOption::findOrFail($unit->$field), 'stock_unit', $unit->id, $raw);
                }
            }
        }
    }
}
