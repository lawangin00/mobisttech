<?php

namespace App\Pos;

use App\Models\Outlet;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PosWarrantyIntakeSearch
{
    private const CATEGORIES = ['all', 'invoice_id', 'customer_name', 'customer_cnic', 'contact_number', 'product', 'imei'];

    public function search(Request $request, Outlet $outlet): array
    {
        if (array_diff(array_keys($request->query()), ['q', 'category', 'product_category'])) {
            throw ValidationException::withMessages(['filter' => 'Unexpected warranty intake search filter.']);
        }
        $input = validator($request->query(), [
            'q' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', self::CATEGORIES)],
            'product_category' => ['sometimes', 'string', 'in:mobile_phone,tablet,accessory'],
        ])->validate();
        $term = trim($input['q']);
        if ($term === '') {
            throw ValidationException::withMessages(['q' => 'Enter a warranty invoice search value.']);
        }
        $category = $input['category'];
        $match = '%'.addcslashes($term, '%_\\').'%';
        $digits = preg_replace('/\D+/', '', $term);
        $rows = DB::table('sales as s')->join('invoices as i', 'i.id', '=', 's.invoice_id')
            ->join('products as p', 'p.id', '=', 's.product_id')
            ->where('s.outlet_id', $outlet->id)->whereColumn('i.outlet_id', 's.outlet_id')
            ->whereColumn('p.outlet_id', 's.outlet_id')
            ->when(isset($input['product_category']), fn (Builder $q) => $q->where('p.category', $input['product_category']))
            ->where(function (Builder $q) use ($category, $match, $digits, $term) {
                foreach (['invoice_id' => 'i.invoice_number', 'customer_name' => 'i.customer_name',
                    'product' => 'p.name'] as $key => $column) {
                    if ($category === 'all' || $category === $key) {
                        $q->orWhere($column, 'like', $match);
                    }
                }
                if (in_array($category, ['all', 'product'], true)) {
                    $q->orWhere('p.product_code', 'like', $match);
                }
                if ($digits !== '' && in_array($category, ['all', 'customer_cnic', 'contact_number'], true)) {
                    foreach (['customer_cnic' => 'i.customer_cnic', 'contact_number' => 'i.customer_phone'] as $key => $column) {
                        if ($category === 'all' || $category === $key) {
                            $q->orWhereRaw("REPLACE($column, '-', '') LIKE ?", ['%'.addcslashes($digits, '%_\\').'%']);
                        }
                    }
                }
                if (in_array($category, ['all', 'imei'], true)) {
                    $q->orWhereExists(fn (Builder $sub) => $sub->selectRaw('1')
                        ->from('product_imeis as pi')->whereColumn('pi.sale_id', 's.id')->where('pi.imei', 'like', $match));
                }
                if (in_array($category, ['all', 'invoice_id'], true) && ctype_digit($term)) {
                    $q->orWhere('i.id', (int) $term);
                }
            })->orderByDesc('i.id')->orderByDesc('s.id')->limit(20)
            ->get(['s.id', 's.public_id', 's.quantity', 's.returned_quantity', 's.invoice_detail_snapshot',
                'i.public_id as invoice_id', 'i.invoice_number', 'i.customer_name', 'i.customer_phone',
                'p.name as product_name', 'p.track_imei']);

        return $rows->map(function ($row) {
            $snapshot = json_decode($row->invoice_detail_snapshot, true) ?: [];
            $units = $row->track_imei ? DB::table('stock_units')->where('sale_id', $row->id)
                ->where('status', 'sold')->get(['public_id', 'unit_code'])
                ->map(fn ($unit) => ['id' => $unit->public_id, 'code' => $unit->unit_code])->all() : [];

            return ['sale_id' => $row->public_id, 'invoice_id' => $row->invoice_id,
                'invoice_number' => $row->invoice_number, 'customer_name' => $row->customer_name,
                'customer_phone' => $row->customer_phone, 'product_name' => $row->product_name,
                'quantity' => (int) $row->quantity, 'returned_quantity' => (int) $row->returned_quantity,
                'track_imei' => (bool) $row->track_imei, 'units' => $units,
                'warranty_type' => $snapshot['warranty_type'] ?? null,
                'warranty_unit' => $snapshot['warranty_unit'] ?? null,
                'warranty_duration' => $snapshot['warranty_duration'] ?? null];
        })->all();
    }
}
