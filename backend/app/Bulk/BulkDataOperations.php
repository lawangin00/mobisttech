<?php

namespace App\Bulk;

use App\Catalog\MasterDataAdministration;
use App\Catalog\ProductDefinitions;
use App\Identity\Access;
use App\Inventory\InventoryOperations;
use App\Inventory\StockLedger;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Services\PosInventoryMasterData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class BulkDataOperations
{
    private const DATASETS = ['catalogue', 'price', 'master_data', 'inventory'];

    private const FORMATS = ['csv', 'xlsx'];

    private const MAX_ROWS = 1000;

    private const MAX_COLUMNS = 64;

    private const MAX_CSV_BYTES = 2097152;

    public function preview(Admin $actor, ?Outlet $outlet, string $dataset, string $format, array|string $document, string $boundary = 'operational'): array
    {
        $this->boundary($boundary);
        $this->authorize($actor, $outlet, $dataset);
        [$headers, $rows] = $this->document($format, $document);
        $this->headers($dataset, $headers);
        $result = [];
        foreach ($rows as $offset => $row) {
            $errors = $this->formulaErrors($row);
            $normalized = null;
            if (! $errors) {
                try {
                    $normalized = $this->normalize($dataset, $outlet, $row);
                } catch (Throwable $error) {
                    $errors = $this->errors($error);
                }
            }
            $result[] = ['row' => $offset + 2, 'row_key' => trim((string) ($row['row_key'] ?? '')),
                'status' => $errors ? 'invalid' : 'valid', 'normalized' => $normalized, 'errors' => $errors];
        }

        return ['dataset' => $dataset, 'format' => $format, 'boundary' => $boundary, 'rows' => $result,
            'valid' => count(array_filter($result, fn ($row) => $row['status'] === 'valid')),
            'invalid' => count(array_filter($result, fn ($row) => $row['status'] === 'invalid'))];
    }

    public function import(Admin $actor, ?Outlet $outlet, string $dataset, string $format, array|string $document,
        string $key, string $recovery = 'whole_batch', string $boundary = 'operational'): array
    {
        if (! in_array($recovery, ['whole_batch', 'row'], true) || trim($key) === '' || strlen($key) > 100) {
            throw ValidationException::withMessages(['import' => 'Recovery mode or idempotency key is invalid.']);
        }
        $preview = $this->preview($actor, $outlet, $dataset, $format, $document, $boundary);
        if ($recovery === 'whole_batch' && $preview['invalid'] > 0) {
            return [...$preview, 'status' => 'rejected', 'recovery' => $recovery, 'applied' => 0];
        }
        $validRows = array_values(array_filter($preview['rows'], fn ($row) => $row['status'] === 'valid'));
        if ($recovery === 'whole_batch') {
            return $this->wholeBatch($actor, $outlet, $dataset, $key, $validRows, $preview);
        }
        $results = [];
        foreach ($preview['rows'] as $row) {
            if ($row['status'] !== 'valid') {
                $results[] = $row;

                continue;
            }
            try {
                $results[] = [...$row, 'status' => 'applied', 'result' => $this->rowApply($actor, $outlet, $dataset, $key, $row['normalized'])];
            } catch (Throwable $error) {
                $results[] = [...$row, 'status' => 'failed', 'errors' => $this->errors($error)];
            }
        }

        return ['dataset' => $dataset, 'format' => $format, 'boundary' => $boundary, 'recovery' => $recovery,
            'status' => collect($results)->contains(fn ($row) => in_array($row['status'], ['invalid', 'failed'], true)) ? 'partial' : 'completed',
            'applied' => count(array_filter($results, fn ($row) => $row['status'] === 'applied')), 'rows' => $results];
    }

    public function export(Admin $actor, ?Outlet $outlet, string $dataset, string $format): array|string
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages(['format' => 'Only CSV and XLSX-style exports are supported.']);
        }
        $this->authorize($actor, $outlet, $dataset);
        $rows = $this->exportRows($dataset, $outlet);
        $headers = $this->exportHeaders($dataset);
        $matrix = [$headers];
        foreach ($rows as $row) {
            $matrix[] = array_map(fn ($header) => $this->safeExportCell($row[$header] ?? null), $headers);
        }
        if ($format === 'xlsx') {
            return ['format' => 'xlsx', 'rows' => $matrix, 'contract' => 'xlsx-cell-matrix.v1'];
        }
        $stream = fopen('php://temp', 'w+');
        foreach ($matrix as $line) {
            fputcsv($stream, $line, ',', '"', '\\', "\r\n");
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function wholeBatch(Admin $actor, ?Outlet $outlet, string $dataset, string $key, array $rows, array $preview): array
    {
        try {
            return DB::transaction(function () use ($actor, $outlet, $dataset, $key, $rows, $preview) {
                $request = $this->request($actor, 'bulk.'.$dataset.'.batch', $key, array_column($rows, 'normalized'));
                if ($request->status === 'completed') {
                    return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
                }
                $results = [];
                foreach ($rows as $row) {
                    $results[] = ['row' => $row['row'], 'row_key' => $row['row_key'], 'status' => 'applied',
                        'result' => $this->apply($actor, $outlet, $dataset, $row['normalized'], $key)];
                }
                $response = ['dataset' => $dataset, 'format' => $preview['format'], 'boundary' => $preview['boundary'],
                    'recovery' => 'whole_batch', 'status' => 'completed', 'applied' => count($results), 'rows' => $results];
                DB::table('idempotency_requests')->where('id', $request->id)->update(['status' => 'completed',
                    'response' => json_encode($response, JSON_THROW_ON_ERROR), 'resource_type' => 'bulk_batch', 'updated_at' => now()]);

                return $response;
            }, 3);
        } catch (Throwable $error) {
            return [...$preview, 'status' => 'failed', 'recovery' => 'whole_batch', 'applied' => 0,
                'batch_errors' => $this->errors($error)];
        }
    }

    private function rowApply(Admin $actor, ?Outlet $outlet, string $dataset, string $batchKey, array $row): array
    {
        return DB::transaction(function () use ($actor, $outlet, $dataset, $batchKey, $row) {
            $key = hash('sha256', $batchKey.'|'.$row['row_key']);
            $request = $this->request($actor, 'bulk.'.$dataset.'.row', $key, $row);
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $this->apply($actor, $outlet, $dataset, $row, $batchKey);
            DB::table('idempotency_requests')->where('id', $request->id)->update(['status' => 'completed',
                'response' => json_encode($response, JSON_THROW_ON_ERROR), 'resource_type' => 'bulk_row', 'updated_at' => now()]);

            return $response;
        }, 3);
    }

    private function request(Admin $actor, string $operation, string $key, array $payload): object
    {
        $payload = $this->canonical($payload);
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $identity = ['actor_scope' => $actor::class.':'.$actor->id, 'operation' => $operation, 'key' => $key];
        DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $hash, 'status' => 'processing',
            'created_at' => now(), 'updated_at' => now()]);
        $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
        if (! hash_equals($request->request_hash, $hash)) {
            throw new LogicException('Bulk idempotency key was already used for different row content.');
        }

        return $request;
    }

    private function apply(Admin $actor, ?Outlet $outlet, string $dataset, array $row, string $batchKey): array
    {
        return match ($dataset) {
            'catalogue' => $this->applyCatalogue($actor, $outlet, $row),
            'price' => $this->applyPrice($actor, $outlet, $row),
            'master_data' => $this->applyMasterData($actor, $row),
            'inventory' => $this->applyInventory($actor, $outlet, $row, $batchKey),
            default => throw new LogicException('Unknown bulk dataset.'),
        };
    }

    private function applyCatalogue(Admin $actor, Outlet $outlet, array $row): array
    {
        $product = app(ProductDefinitions::class)->save($actor, $outlet, $row['input'], $row['product_id']);

        return ['product_id' => $product->public_id, 'product_code' => $product->product_code, 'version' => $product->version];
    }

    private function applyPrice(Admin $actor, Outlet $outlet, array $row): array
    {
        $product = Product::where('public_id', $row['product_id'])->where('outlet_id', $outlet->id)->where('isDeleted', false)->firstOrFail();
        $input = $this->productInput($product, ['purchase_price' => $row['purchase_price'], 'sale_price' => $row['sale_price']]);
        $product = app(ProductDefinitions::class)->save($actor, $outlet, $input, $product->public_id);

        return ['product_id' => $product->public_id, 'purchase_price' => $product->purchase_price, 'sale_price' => $product->sale_price, 'version' => $product->version];
    }

    private function applyMasterData(Admin $actor, array $row): array
    {
        $input = array_filter($row['input'], fn ($value) => $value !== null, ARRAY_FILTER_USE_BOTH);
        $option = app(MasterDataAdministration::class)->change($actor, $row['action'], $row['list'], $input, $row['option_id']);

        return ['option_id' => $option?->id ?? $row['option_id'], 'action' => $row['action'], 'list' => $row['list']];
    }

    private function applyInventory(Admin $actor, Outlet $outlet, array $row, string $batchKey): array
    {
        $key = 'bulk-'.substr(hash('sha256', $batchKey.'|'.$row['row_key']), 0, 60);
        if ($row['action'] === 'acquire') {
            return app(InventoryOperations::class)->acquire($actor, $outlet, $row['product_id'], $key, $row['input']);
        }

        return app(InventoryOperations::class)->adjust($actor, $outlet, $row['product_id'], $key, $row['input']);
    }

    private function normalize(string $dataset, ?Outlet $outlet, array $row): array
    {
        $row = array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
        $rowKey = trim((string) ($row['row_key'] ?? ''));
        if ($rowKey === '' || strlen($rowKey) > 80 || ! preg_match('/\A[A-Za-z0-9._:-]+\z/', $rowKey)) {
            throw ValidationException::withMessages(['row_key' => 'row_key is required and may contain only A-Z, 0-9, dot, underscore, colon or dash.']);
        }

        return match ($dataset) {
            'catalogue' => $this->normalizeCatalogue($outlet, $rowKey, $row),
            'price' => $this->normalizePrice($outlet, $rowKey, $row),
            'master_data' => $this->normalizeMasterData($rowKey, $row),
            'inventory' => $this->normalizeInventory($outlet, $rowKey, $row),
            default => throw new LogicException('Unknown bulk dataset.'),
        };
    }

    private function normalizeCatalogue(Outlet $outlet, string $rowKey, array $row): array
    {
        $this->required($row, ['name', 'category', 'purchase_price', 'sale_price', 'warranty_type']);
        if (! array_key_exists((string) $row['category'], Product::CATEGORY_LABELS)) {
            throw ValidationException::withMessages(['category' => 'Unknown product category.']);
        }
        $publicId = $this->nullable($row['product_id'] ?? null);
        if ($publicId !== null && (! Str::isUuid($publicId) || ! Product::where('public_id', $publicId)->where('outlet_id', $outlet->id)->where('isDeleted', false)->exists())) {
            throw ValidationException::withMessages(['product_id' => 'Product does not exist in this outlet.']);
        }
        $subcategory = null;
        if (($code = $this->nullable($row['subcategory_code'] ?? null)) !== null) {
            $subcategory = app(PosInventoryMasterData::class)->optionByCode('product_subcategory', $code);
            if (! $subcategory || ! $subcategory->is_active || $subcategory->archived_at !== null) {
                throw ValidationException::withMessages(['subcategory_code' => 'Subcategory is not active.']);
            }
        }
        $input = ['name' => (string) $row['name'], 'category' => (string) $row['category'], 'brand' => $this->nullable($row['brand'] ?? null),
            'model' => $this->nullable($row['model'] ?? null), 'description' => $this->nullable($row['description'] ?? null),
            'purchase_price' => SourceRow::money((string) $row['purchase_price']), 'sale_price' => SourceRow::money((string) $row['sale_price']),
            'track_imei' => $this->boolean($row['track_imei'] ?? false), 'warranty_type' => (string) $row['warranty_type'],
            'warranty_unit' => $this->integer($row['warranty_unit'] ?? null), 'warranty_duration' => $this->integer($row['warranty_duration'] ?? null),
            'ram_gb' => $this->integer($row['ram_gb'] ?? null), 'storage_gb' => $this->integer($row['storage_gb'] ?? null),
            'sim_configuration' => $this->nullable($row['sim_configuration'] ?? null), 'subcategory_master_data_id' => $subcategory?->id];

        return ['row_key' => $rowKey, 'product_id' => $publicId, 'input' => $input];
    }

    private function normalizePrice(Outlet $outlet, string $rowKey, array $row): array
    {
        $this->required($row, ['product_id', 'purchase_price', 'sale_price']);
        $publicId = (string) $row['product_id'];
        if (! Str::isUuid($publicId) || ! Product::where('public_id', $publicId)->where('outlet_id', $outlet->id)->where('isDeleted', false)->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Product does not exist in this outlet.']);
        }

        return ['row_key' => $rowKey, 'product_id' => $publicId,
            'purchase_price' => SourceRow::money((string) $row['purchase_price']), 'sale_price' => SourceRow::money((string) $row['sale_price'])];
    }

    private function normalizeMasterData(string $rowKey, array $row): array
    {
        $this->required($row, ['list', 'action']);
        $list = (string) $row['list'];
        $action = (string) $row['action'];
        if (! array_key_exists($list, PosInventoryMasterData::LISTS) || ! in_array($action, ['create', 'update', 'activate', 'deactivate', 'archive', 'restore'], true)) {
            throw ValidationException::withMessages(['master_data' => 'Unknown managed list or bulk-safe action.']);
        }
        $optionId = $this->integer($row['option_id'] ?? null);
        if ($action !== 'create' && (! $optionId || ! PosMasterDataOption::whereKey($optionId)->where('list_key', $list)->exists())) {
            throw ValidationException::withMessages(['option_id' => 'Existing option_id is required for this action.']);
        }
        $input = in_array($action, ['activate', 'deactivate', 'archive', 'restore'], true) ? [] : [
            'label' => $this->nullable($row['label'] ?? null), 'sort_order' => $this->integer($row['sort_order'] ?? null),
            'hex' => $this->nullable($row['hex'] ?? null), 'value' => $this->integer($row['value'] ?? null),
            'imei_slots' => $this->integer($row['imei_slots'] ?? null), 'parent_category_code' => $this->nullable($row['parent_category_code'] ?? null),
            'party_kind' => $this->nullable($row['party_kind'] ?? null)];

        return ['row_key' => $rowKey, 'list' => $list, 'action' => $action, 'option_id' => $optionId, 'input' => $input];
    }

    private function normalizeInventory(Outlet $outlet, string $rowKey, array $row): array
    {
        $this->required($row, ['product_id', 'action', 'quantity', 'reason']);
        $publicId = (string) $row['product_id'];
        if (! Str::isUuid($publicId) || ! Product::where('public_id', $publicId)->where('outlet_id', $outlet->id)->where('isDeleted', false)->exists()) {
            throw ValidationException::withMessages(['product_id' => 'Product does not exist in this outlet.']);
        }
        $quantity = $this->integer($row['quantity']);
        if (! $quantity || $quantity < 1 || $quantity > 10000) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be between 1 and 10000.']);
        }
        $action = (string) $row['action'];
        if ($action === 'acquire') {
            $this->required($row, ['unit_purchase_price', 'source_type_code', 'seller_phone', 'seller_address']);
            $source = app(PosInventoryMasterData::class)->optionByCode('acquisition_source_type', (string) $row['source_type_code']);
            if (! $source || ! $source->is_active || $source->archived_at !== null) {
                throw ValidationException::withMessages(['source_type_code' => 'Buying source type is not active.']);
            }
            $input = ['quantity' => $quantity, 'unit_purchase_price' => SourceRow::money((string) $row['unit_purchase_price']),
                'source_type_master_data_id' => $source->id, 'business_name' => $this->nullable($row['business_name'] ?? null),
                'seller_name' => $this->nullable($row['seller_name'] ?? null), 'seller_cnic' => $this->nullable($row['seller_cnic'] ?? null),
                'seller_phone' => (string) $row['seller_phone'], 'seller_address' => (string) $row['seller_address'], 'reason' => (string) $row['reason']];
        } elseif ($action === 'adjust') {
            $type = (string) ($row['type'] ?? '');
            if (! in_array($type, ['correction_in', 'correction_out', 'damaged', 'lost'], true)) {
                throw ValidationException::withMessages(['type' => 'Unknown inventory adjustment type.']);
            }
            $input = ['type' => $type, 'quantity' => $quantity, 'reason' => (string) $row['reason'], 'unit_id' => $this->nullable($row['unit_id'] ?? null)];
        } else {
            throw ValidationException::withMessages(['action' => 'Inventory bulk action must be acquire or adjust.']);
        }

        return ['row_key' => $rowKey, 'product_id' => $publicId, 'action' => $action, 'input' => $input];
    }

    private function document(string $format, array|string $document): array
    {
        if (! in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages(['format' => 'Only CSV and XLSX-style documents are supported.']);
        }
        if ($format === 'csv') {
            if (! is_string($document) || strlen($document) > self::MAX_CSV_BYTES || str_contains($document, "\0")) {
                throw ValidationException::withMessages(['file' => 'CSV payload is invalid or too large.']);
            }
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, $document);
            rewind($stream);
            $matrix = [];
            while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
                $matrix[] = $row;
                if (count($matrix) > self::MAX_ROWS + 1) {
                    throw ValidationException::withMessages(['file' => 'Bulk documents are limited to 1000 data rows.']);
                }
            }
            fclose($stream);
        } else {
            if (! is_array($document) || count($document) > self::MAX_ROWS + 1 || array_filter($document, fn ($row) => ! is_array($row))) {
                throw ValidationException::withMessages(['file' => 'XLSX-style input must be a cell matrix with at most 1000 data rows.']);
            }
            $matrix = array_values($document);
        }
        if (! $matrix) {
            throw ValidationException::withMessages(['file' => 'Bulk document is empty.']);
        }
        $headers = array_map(fn ($value) => trim((string) $value), array_shift($matrix));
        if (! $headers || count($headers) > self::MAX_COLUMNS || in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) {
            throw ValidationException::withMessages(['headers' => 'Headers must be unique, non-empty and limited to 64 columns.']);
        }
        $rows = [];
        foreach ($matrix as $row) {
            if (count($row) > count($headers)) {
                throw ValidationException::withMessages(['file' => 'A data row contains more cells than the header.']);
            }
            $row = array_pad(array_values($row), count($headers), null);
            $rows[] = array_combine($headers, $row);
        }

        return [$headers, $rows];
    }

    private function headers(string $dataset, array $headers): void
    {
        $spec = $this->spec($dataset);
        $unknown = array_values(array_diff($headers, $spec['allowed']));
        $missing = array_values(array_diff($spec['required_headers'], $headers));
        if ($unknown || $missing) {
            throw ValidationException::withMessages(['headers' => 'Bulk headers are outside the dataset contract.',
                'unknown' => implode(', ', $unknown), 'missing' => implode(', ', $missing)]);
        }
    }

    private function formulaErrors(array $row): array
    {
        $errors = [];
        foreach ($row as $field => $value) {
            if (is_string($value) && preg_match('/\A\s*[=+\-@\t\r]/u', $value)) {
                $errors[$field][] = 'Formula-leading cells are rejected; submit literal values only.';
            }
        }

        return $errors;
    }

    private function spec(string $dataset): array
    {
        if (! in_array($dataset, self::DATASETS, true)) {
            throw ValidationException::withMessages(['dataset' => 'Unknown bulk dataset.']);
        }

        return match ($dataset) {
            'catalogue' => ['required_headers' => ['row_key', 'name', 'category', 'purchase_price', 'sale_price', 'warranty_type'],
                'allowed' => ['row_key', 'product_id', 'name', 'category', 'subcategory_code', 'brand', 'model', 'description',
                    'purchase_price', 'sale_price', 'track_imei', 'warranty_type', 'warranty_unit', 'warranty_duration',
                    'ram_gb', 'storage_gb', 'sim_configuration']],
            'price' => ['required_headers' => ['row_key', 'product_id', 'purchase_price', 'sale_price'],
                'allowed' => ['row_key', 'product_id', 'purchase_price', 'sale_price']],
            'master_data' => ['required_headers' => ['row_key', 'list', 'action'],
                'allowed' => ['row_key', 'list', 'action', 'option_id', 'label', 'sort_order', 'hex', 'value', 'imei_slots',
                    'parent_category_code', 'party_kind']],
            'inventory' => ['required_headers' => ['row_key', 'product_id', 'action', 'quantity', 'reason'],
                'allowed' => ['row_key', 'product_id', 'action', 'quantity', 'unit_purchase_price', 'source_type_code',
                    'business_name', 'seller_name', 'seller_cnic', 'seller_phone', 'seller_address', 'reason', 'type', 'unit_id']],
        };
    }

    private function exportRows(string $dataset, ?Outlet $outlet): array
    {
        return match ($dataset) {
            'catalogue' => $this->catalogueExport($outlet),
            'price' => $this->priceExport($outlet),
            'master_data' => $this->masterExport(),
            'inventory' => $this->inventoryExport($outlet),
        };
    }

    private function catalogueExport(Outlet $outlet): array
    {
        return Product::with(['subcategoryMasterOption', 'brandMasterOption'])->where('outlet_id', $outlet->id)->where('isDeleted', false)
            ->orderBy('id')->get()->map(fn (Product $product) => [
                'row_key' => $product->public_id, 'product_id' => $product->public_id, 'product_code' => $product->product_code,
                'name' => $product->name, 'category' => $product->category, 'subcategory_code' => $product->subcategoryMasterOption?->code,
                'brand' => $product->brandMasterOption?->label ?? $product->brand, 'model' => $product->model, 'description' => $product->description,
                'purchase_price' => SourceRow::money((string) $product->purchase_price), 'sale_price' => SourceRow::money((string) $product->sale_price),
                'track_imei' => $product->track_imei ? '1' : '0', 'warranty_type' => $product->warranty_type,
                'warranty_unit' => $product->warranty_unit, 'warranty_duration' => $product->warranty_duration,
                'ram_gb' => $product->ram_gb, 'storage_gb' => $product->storage_gb, 'sim_configuration' => $product->sim_configuration,
            ])->all();
    }

    private function priceExport(Outlet $outlet): array
    {
        return Product::where('outlet_id', $outlet->id)->where('isDeleted', false)->orderBy('id')->get()
            ->map(fn (Product $product) => ['row_key' => $product->public_id, 'product_id' => $product->public_id,
                'product_code' => $product->product_code, 'name' => $product->name,
                'purchase_price' => SourceRow::money((string) $product->purchase_price), 'sale_price' => SourceRow::money((string) $product->sale_price)])->all();
    }

    private function masterExport(): array
    {
        return PosMasterDataOption::whereIn('list_key', array_keys(PosInventoryMasterData::LISTS))->orderBy('list_key')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PosMasterDataOption $option) => ['row_key' => $option->list_key.':'.$option->id, 'list' => $option->list_key,
                'option_id' => $option->id, 'code' => $option->code, 'label' => $option->label, 'sort_order' => $option->sort_order,
                'active' => $option->is_active ? '1' : '0', 'archived' => $option->archived_at ? '1' : '0',
                'hex' => data_get($option->metadata, 'hex'), 'value' => data_get($option->metadata, 'value'),
                'imei_slots' => data_get($option->metadata, 'imei_slots'), 'parent_category_code' => data_get($option->metadata, 'parent_category_code'),
                'party_kind' => data_get($option->metadata, 'party_kind')])->all();
    }

    private function inventoryExport(Outlet $outlet): array
    {
        return Product::where('outlet_id', $outlet->id)->where('isDeleted', false)->orderBy('id')->get()->map(function (Product $product) {
            $snapshot = app(StockLedger::class)->snapshot($product->id);

            return ['product_id' => $product->public_id, 'product_code' => $product->product_code, 'name' => $product->name,
                'track_imei' => $product->track_imei ? '1' : '0', 'on_hand' => $snapshot['on_hand'],
                'held' => $snapshot['held'], 'available' => $snapshot['available']];
        })->all();
    }

    private function exportHeaders(string $dataset): array
    {
        return match ($dataset) {
            'catalogue' => ['row_key', 'product_id', 'product_code', 'name', 'category', 'subcategory_code', 'brand', 'model', 'description',
                'purchase_price', 'sale_price', 'track_imei', 'warranty_type', 'warranty_unit', 'warranty_duration', 'ram_gb', 'storage_gb', 'sim_configuration'],
            'price' => ['row_key', 'product_id', 'product_code', 'name', 'purchase_price', 'sale_price'],
            'master_data' => ['row_key', 'list', 'option_id', 'code', 'label', 'sort_order', 'active', 'archived', 'hex', 'value', 'imei_slots',
                'parent_category_code', 'party_kind'],
            'inventory' => ['product_id', 'product_code', 'name', 'track_imei', 'on_hand', 'held', 'available'],
            default => throw ValidationException::withMessages(['dataset' => 'Unknown bulk dataset.']),
        };
    }

    private function safeExportCell(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/\A\s*[=+\-@\t\r]/u', $value)) {
            return "'".$value;
        }

        return $value;
    }

    private function productInput(Product $product, array $overrides = []): array
    {
        return [...[
            'name' => $product->name, 'category' => $product->category, 'brand' => $product->brand, 'model' => $product->model,
            'description' => $product->description, 'purchase_price' => (string) $product->purchase_price, 'sale_price' => (string) $product->sale_price,
            'track_imei' => (bool) $product->track_imei, 'warranty_type' => $product->warranty_type, 'warranty_unit' => $product->warranty_unit,
            'warranty_duration' => $product->warranty_duration, 'ram_gb' => $product->ram_gb, 'storage_gb' => $product->storage_gb,
            'sim_configuration' => $product->sim_configuration, 'category_master_data_id' => $product->category_master_data_id,
            'subcategory_master_data_id' => $product->subcategory_master_data_id, 'brand_master_data_id' => $product->brand_master_data_id,
            'ram_master_data_id' => $product->ram_master_data_id, 'storage_master_data_id' => $product->storage_master_data_id,
            'sim_master_data_id' => $product->sim_master_data_id,
        ], ...$overrides];
    }

    private function authorize(Admin $actor, ?Outlet $outlet, string $dataset): void
    {
        $this->spec($dataset);
        $fresh = $actor->fresh();
        if ($dataset === 'master_data') {
            abort_unless($fresh && app(Access::class)->allows($fresh, 'config.master-data.manage'), 403);

            return;
        }
        abort_unless($fresh && $outlet && app(Access::class)->allows($fresh, 'shop.inventory', $outlet->fresh()), 403);
    }

    private function boundary(string $boundary): void
    {
        if ($boundary !== 'operational') {
            throw ValidationException::withMessages(['boundary' => 'Historical imports must use the dedicated offline migration/rehearsal importers, never operational bulk services.']);
        }
    }

    private function required(array $row, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field, $row) || $row[$field] === null || (is_string($row[$field]) && trim($row[$field]) === '')) {
                $missing[] = $field;
            }
        }
        if ($missing) {
            throw ValidationException::withMessages(['required' => 'Missing required values: '.implode(', ', $missing)]);
        }
    }

    private function nullable(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw ValidationException::withMessages(['boolean' => 'Boolean value must be true/false, yes/no or 1/0.']);
        }

        return $parsed;
    }

    private function integer(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_int($value) && ! preg_match('/\A-?\d+\z/', (string) $value)) {
            throw ValidationException::withMessages(['integer' => 'Expected an integer value.']);
        }

        return (int) $value;
    }

    private function errors(Throwable $error): array
    {
        if ($error instanceof ValidationException) {
            return $error->errors();
        }
        if ($error instanceof ModelNotFoundException
            || $error instanceof RecordNotFoundException) {
            return ['row' => ['Referenced record was not found.']];
        }
        if ($error instanceof QueryException) {
            return ['row' => ['Row violates a database constraint.']];
        }
        if ($error instanceof HttpExceptionInterface) {
            return ['row' => ['Operation was rejected with HTTP '.$error->getStatusCode().'.']];
        }

        return ['row' => [trim($error->getMessage()) ?: 'Bulk row failed.']];
    }

    private function canonical(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
