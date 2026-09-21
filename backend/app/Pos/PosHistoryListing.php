<?php

namespace App\Pos;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PosHistoryListing
{
    private const FIELDS = [
        'invoices' => ['invoice_id' => 'i.invoice_number', 'customer_name' => 'i.customer_name',
            'contact_number' => 'i.customer_phone', 'invoice_date' => 'i.created_at', 'total' => 'i.final_bill'],
        'warranty' => ['invoice_id' => 'i.invoice_number', 'customer_name' => 'i.customer_name',
            'customer_cnic' => 'i.customer_cnic', 'contact_number' => 'i.customer_phone', 'product' => 'p.name'],
        'claims' => ['claim' => 'c.claim_number', 'invoice' => 'i.invoice_number',
            'customer' => 'i.customer_name', 'contact' => 'i.customer_phone', 'product' => 'p.name',
            'status' => 'c.status', 'assigned' => 'c.assigned_to'],
    ];

    public function page(Request $request, string $area, Builder $query): array
    {
        abort_unless(isset(self::FIELDS[$area]), 404);
        $preferences = app(PortalPreferences::class)->current();
        $options = app(PortalPreferences::class)->catalogueOptions()[$area === 'invoices' ? 'invoice_search_category' : ($area === 'warranty' ? 'warranty_search_category' : 'claims_search_category')];
        if (array_diff(array_keys($request->query()), ['q', 'category', 'page'])) {
            throw ValidationException::withMessages(['filter' => 'Unexpected list filter.']);
        }
        $input = validator($request->query(), ['q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'in:'.implode(',', $options)],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000']])->validate();
        $category = $input['category'] ?? $preferences[$area === 'invoices' ? 'invoice_search_category' : ($area === 'warranty' ? 'warranty_search_category' : 'claims_search_category')];
        $search = trim((string) ($input['q'] ?? ''));
        if ($search !== '') {
            $match = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $where) use ($area, $category, $match) {
                $fields = self::FIELDS[$area];
                foreach ($fields as $key => $column) {
                    if ($category === 'all' || $category === $key) {
                        $where->orWhere($column, 'like', $match);
                    }
                }
                if ($area === 'invoices' && in_array($category, ['all', 'item', 'imei'], true)) {
                    if ($category !== 'imei') {
                        $where->orWhereExists(fn (Builder $related) => $related->selectRaw('1')
                            ->from('sales as s')->join('products as p', 'p.id', '=', 's.product_id')
                            ->whereColumn('s.invoice_id', 'i.id')->whereColumn('s.outlet_id', 'i.outlet_id')
                            ->whereColumn('p.outlet_id', 'i.outlet_id')->where('p.name', 'like', $match));
                    }
                    if ($category !== 'item') {
                        $where->orWhereExists(fn (Builder $related) => $related->selectRaw('1')
                            ->from('product_imeis as imei')->whereColumn('imei.invoice_id', 'i.id')
                            ->where('imei.imei', 'like', $match));
                    }
                }
                if (in_array($area, ['claims', 'warranty'], true) && in_array($category, ['all', 'imei'], true)) {
                    $where->orWhereExists(fn (Builder $related) => $related->selectRaw('1')
                        ->from('product_imeis as imei')->whereColumn('imei.stock_unit_id', 'c.stock_unit_id')
                        ->whereColumn('imei.invoice_id', 'c.invoice_id')->where('imei.imei', 'like', $match));
                }
            });
        }
        $perPage = (int) $preferences[$area === 'invoices' ? 'invoice_page_length' : 'claims_page_length'];
        $page = $query->orderByDesc($area === 'invoices' ? 'i.id' : 'c.id')
            ->paginate($perPage, $area === 'invoices' ? ['i.*'] : ['c.*', 'i.public_id as invoice_id',
                'i.invoice_number', 'i.customer_name', 'i.customer_phone', 'p.name as product_name'],
                'page', (int) ($input['page'] ?? 1));

        return ['rows' => $page->items(), 'pagination' => [
            'page' => $page->currentPage(), 'pages' => $page->lastPage(),
            'total' => $page->total(), 'per_page' => $perPage,
            'q' => $search, 'category' => $category,
            'options' => array_values($options), 'auto_focus_search' => $preferences['auto_focus_search'],
            'remember_search' => $preferences['remember_search'],
        ]];
    }
}
