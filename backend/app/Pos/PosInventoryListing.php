<?php

namespace App\Pos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PosInventoryListing
{
    private const PRODUCT_FIELDS = [
        'product_name' => ['name'], 'sku' => ['sku', 'product_code'],
        'category' => ['category'], 'brand' => ['brand'], 'model' => ['model'],
        'purchase_price' => ['purchase_price'], 'sale_price' => ['sale_price'],
        'warranty' => ['warranty_type'],
    ];

    private const UNIT_FIELDS = [
        'color' => 'color', 'condition' => 'condition', 'pta_status' => 'pta_status',
        'carrier_lock' => 'carrier_lock_status', 'mdm_status' => 'mdm_status',
    ];

    public function page(Request $request, Builder $query): array
    {
        $prefs = app(PortalPreferences::class)->current();
        $options = app(PortalPreferences::class)->catalogueOptions()['inventory_search_category'];
        if (array_diff(array_keys($request->query()), ['mode', 'q', 'category', 'page'])) {
            throw ValidationException::withMessages(['filter' => 'Unexpected inventory filter.']);
        }
        $input = validator($request->query(), [
            'mode' => ['required', 'in:inventory'], 'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'in:'.implode(',', $options)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ])->validate();
        $category = $input['category'] ?? $prefs['inventory_search_category'];
        $search = trim((string) ($input['q'] ?? ''));
        if ($search !== '') {
            $match = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $filters) use ($category, $match) {
                foreach (self::PRODUCT_FIELDS as $key => $fields) {
                    if ($category !== 'all' && $category !== $key) {
                        continue;
                    }
                    foreach ($fields as $column) {
                        $filters->orWhere('products.'.$column, 'like', $match);
                    }
                }
                foreach (self::UNIT_FIELDS as $key => $column) {
                    if ($category !== 'all' && $category !== $key) {
                        continue;
                    }
                    $filters->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('stock_units as su')
                        ->whereColumn('su.product_id', 'products.id')->where('su.'.$column, 'like', $match));
                }
                if (in_array($category, ['all', 'imei'], true)) {
                    $filters->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('product_imeis as pi')
                        ->whereColumn('pi.product_id', 'products.id')->where('pi.imei', 'like', $match));
                }
                if (in_array($category, ['all', 'variant'], true)) {
                    foreach (['ram_gb', 'storage_gb'] as $column) {
                        $filters->orWhereRaw('CAST(products.'.$column.' AS CHAR) LIKE ?', [$match]);
                    }
                }
                foreach (['in_stock' => 'qty', 'sold' => 'sold_qty'] as $key => $column) {
                    if ($category === 'all' || $category === $key) {
                        $filters->orWhereRaw('CAST(products.'.$column.' AS CHAR) LIKE ?', [$match]);
                    }
                }
            });
        }
        match ($prefs['inventory_filter']) {
            'in_stock' => $query->where('qty', '>', 0),
            'out_of_stock' => $query->where('qty', 0),
            'imei_tracked' => $query->where('track_imei', true),
            default => null,
        };
        $size = (int) $prefs['inventory_page_length'];
        match ($prefs['inventory_sort']) {
            'stock_low' => $query->orderBy('qty')->orderBy('name'),
            'stock_high' => $query->orderByDesc('qty')->orderBy('name'),
            'sale_high' => $query->orderByDesc('sale_price')->orderBy('name'),
            'sale_low' => $query->orderBy('sale_price')->orderBy('name'),
            default => $query->orderBy('name'),
        };
        $page = $query->orderBy('id')->paginate($size, ['products.*'], 'page', (int) ($input['page'] ?? 1));

        return ['rows' => $page->items(), 'paging' => [
            'page' => $page->currentPage(), 'pages' => $page->lastPage(), 'total' => $page->total(),
            'per_page' => $size, 'q' => $search, 'category' => $category, 'options' => $options,
            'auto_focus_search' => $prefs['auto_focus_search'], 'remember_search' => $prefs['remember_search'],
            'density' => $prefs['inventory_density'],
            'sort' => $prefs['inventory_sort'], 'filter' => $prefs['inventory_filter'],
            'columns' => app(PortalPreferences::class)->columnsFor('inventory'),
        ]];
    }
}
