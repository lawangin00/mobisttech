<?php

namespace App\Catalog;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Inventory\StockLedger;
use App\Migration\SourceRow;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Services\PosInventoryMasterData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ProductDefinitions
{
    public const PRODUCT_OPTIONS = ['category_master_data_id' => ['product_category', 'category'], 'subcategory_master_data_id' => ['product_subcategory', 'subcategory'],
        'brand_master_data_id' => ['product_brand', 'brand'], 'ram_master_data_id' => ['device_ram_gb', 'ram_gb'],
        'storage_master_data_id' => ['device_storage_gb', 'storage_gb'], 'sim_master_data_id' => ['device_sim_configuration', 'sim_configuration']];

    public const UNIT_OPTIONS = ['color_master_data_id' => ['unit_color', 'color'], 'condition_master_data_id' => ['unit_condition', 'condition'],
        'pta_status_master_data_id' => ['unit_pta_status', 'pta_status'], 'carrier_lock_master_data_id' => ['unit_carrier_lock_status', 'carrier_lock_status'],
        'mdm_status_master_data_id' => ['unit_mdm_status', 'mdm_status']];

    // Creates zero-stock definitions only; acquisitions/opening stock are owned by MT-2.4.
    public function save(IdentityAccount $actor, Outlet $outlet, array $input, ?string $publicId = null): Product
    {
        return DB::transaction(function () use ($actor, $outlet, $input, $publicId) {
            $outlet = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $outlet);
            $product = $publicId ? Product::where('public_id', $publicId)->where('outlet_id', $outlet->id)->where('isDeleted', false)->lockForUpdate()->firstOrFail() : new Product;
            $rules = ['name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->where('outlet_id', $outlet->id)->where('isDeleted', false)->ignore($product->id)],
                'category' => ['required', Rule::in(array_keys(Product::CATEGORY_LABELS))], 'brand' => 'nullable|string|max:100',
                'model' => 'required_unless:category,accessory|nullable|string|max:150', 'description' => 'nullable|string|max:5000',
                'purchase_price' => 'required', 'sale_price' => 'required', 'track_imei' => 'sometimes|boolean',
                'warranty_type' => ['required', Rule::in(['shop_warranty', 'brand_warranty', 'no_warranty'])],
                'warranty_unit' => 'required_unless:warranty_type,no_warranty|nullable|integer|min:0|max:2',
                'warranty_duration' => 'required_unless:warranty_type,no_warranty|nullable|integer|min:1',
                'ram_gb' => 'nullable|integer|min:1|max:2048', 'storage_gb' => 'nullable|integer|min:1|max:8192', 'sim_configuration' => 'nullable|string|max:40'];
            foreach (array_keys(self::PRODUCT_OPTIONS) as $field) {
                $rules[$field] = 'nullable|integer|min:1';
            }
            $this->fields($input, array_keys($rules));
            $input['name'] = trim((string) ($input['name'] ?? ''));
            $data = Validator::make($input, $rules)->validate();
            try {
                $data['purchase_price'] = SourceRow::money($data['purchase_price']);
                $data['sale_price'] = SourceRow::money($data['sale_price']);
            } catch (\InvalidArgumentException $error) {
                throw ValidationException::withMessages(['price' => $error->getMessage()]);
            }
            $master = app(PosInventoryMasterData::class);
            $options = [];
            foreach (self::PRODUCT_OPTIONS as $field => [$list, $raw]) {
                if ($data['category'] === 'accessory' && in_array($field, ['ram_master_data_id', 'storage_master_data_id', 'sim_master_data_id'], true)) {
                    $options[$field] = null;

                    continue;
                }
                $options[$field] = $master->resolveSubmission($list, array_key_exists($field, $data) ? $data[$field] : $product->$field,
                    $data[$raw] ?? null, $product->$field);
            }
            $lockedOptions = PosMasterDataOption::whereIn('id', array_filter(array_map(fn ($option) => $option?->id, $options)))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($options as $field => $option) {
                if ($option) {
                    $options[$field] = $lockedOptions->get($option->id);
                    if (! $options[$field] || ((! $options[$field]->is_active || $options[$field]->archived_at !== null) && $option->id !== $product->$field)) {
                        throw ValidationException::withMessages(['master_data' => 'The selected option changed or is no longer active.']);
                    }
                }
            }
            $category = $options['category_master_data_id'];
            $subcategory = $options['subcategory_master_data_id'];
            if (! $category || $category->code !== $data['category'] || ($subcategory && $master->subcategoryParentCode($subcategory) !== $category->code)) {
                throw ValidationException::withMessages(['category' => 'Category and subcategory must match the protected category.']);
            }
            if ($category->code !== 'accessory' && ! $options['brand_master_data_id']) {
                throw ValidationException::withMessages(['brand' => 'A managed brand is required for devices.']);
            }
            $track = $category->code !== 'accessory' && (bool) ($data['track_imei'] ?? $product->track_imei);
            if ($product->exists && ($product->category !== $category->code || (bool) $product->track_imei !== $track)
                && ($product->qty > 0 || $product->stockUnits()->exists() || $this->held($product->id))) {
                throw ValidationException::withMessages(['category' => 'Stock/history must be reconciled before changing category or tracking mode.']);
            }
            if ($product->exists && $this->held($product->id)) {
                throw ValidationException::withMessages(['product' => 'A held product cannot change definition.']);
            }
            foreach (self::PRODUCT_OPTIONS as $field => [$list, $raw]) {
                if ($product->exists) {
                    $master->syncUsage($product->$field, $options[$field], 'product', $product->id, $raw);
                }
                $data[$field] = $options[$field]?->id;
            }
            $data['brand'] = $options['brand_master_data_id']?->label;
            $data['ram_gb'] = $options['ram_master_data_id'] ? $master->value($options['ram_master_data_id']) : null;
            $data['storage_gb'] = $options['storage_master_data_id'] ? $master->value($options['storage_master_data_id']) : null;
            $data['sim_configuration'] = $options['sim_master_data_id']?->code ?? ($category->code === 'accessory' ? 'not_applicable' : ($product->sim_configuration === 'unknown' ? 'unknown' : null));
            if ($data['warranty_type'] === 'no_warranty') {
                $data['warranty_unit'] = $data['warranty_duration'] = null;
            }
            $product->forceFill([...$data, 'outlet_id' => $outlet->id, 'price' => $data['sale_price'], 'track_imei' => $track,
                'version' => ($product->version ?? 0) + 1])->save();
            foreach (self::PRODUCT_OPTIONS as $field => [$list, $raw]) {
                $master->syncUsage(null, $options[$field], 'product', $product->id, $raw);
            }
            CatalogChanged::record('product', $product->public_id, $product->version);

            return $product->refresh();
        }, 3);
    }

    public function unitAttributes(IdentityAccount $actor, Outlet $outlet, string $unitId, array $input): StockUnit
    {
        return DB::transaction(function () use ($actor, $outlet, $unitId, $input) {
            $this->authorize($actor, $outlet->fresh());
            $unit = StockUnit::where('public_id', $unitId)->firstOrFail();
            $product = Product::whereKey($unit->product_id)->where('outlet_id', $outlet->id)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
            $unit = StockUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            abort_if($unit->status !== 'in_stock' || $this->held($product->id), 409);
            $this->fields($input, array_keys(self::UNIT_OPTIONS));
            $master = app(PosInventoryMasterData::class);
            $lockedOptions = PosMasterDataOption::whereIn('id', array_filter(array_values($input)))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($input as $field => $id) {
                [$list, $raw] = self::UNIT_OPTIONS[$field];
                $option = $master->resolveSubmission($list, $id, null, $unit->$field);
                if ($option) {
                    $option = $lockedOptions->get($option->id);
                    if (! $option || ((! $option->is_active || $option->archived_at !== null) && $option->id !== $unit->$field)) {
                        throw ValidationException::withMessages(['master_data' => 'The selected option changed or is no longer active.']);
                    }
                }
                $master->syncUsage($unit->$field, $option, 'stock_unit', $unit->id, $raw);
                $unit->$field = $option?->id;
                $unit->$raw = $option ? ($raw === 'color' ? $option->label : $option->code) : null;
            }
            $unit->version = ($unit->version ?? 0) + 1;
            $unit->save();
            CatalogChanged::record('stock_unit', $unit->public_id, $unit->version);

            return $unit->refresh();
        }, 3);
    }

    private function authorize(IdentityAccount $actor, Outlet $outlet): void
    {
        $fresh = $actor->fresh();
        abort_unless($fresh && app(Access::class)->allows($fresh, 'shop.inventory', $outlet), 403);
    }

    private function held(int $id): bool
    {
        return app(StockLedger::class)->holds($id)->isNotEmpty()
            || DB::table('inventory_custody_holds')->where('destination_product_id', $id)->whereNull('released_at')->exists();
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['request' => 'Unexpected product fields. Stock, actors and identifiers are server-owned.']);
        }
    }
}
