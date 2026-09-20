<?php

namespace App\Reporting;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Models\Outlet;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class OperationalReports
{
    public function summary(IdentityAccount $actor, Outlet $outlet, ?string $from = null, ?string $to = null): array
    {
        abort_unless(app(Access::class)->allows($actor, 'reports.view', $outlet), 403);
        Validator::make(['from' => $from, 'to' => $to], [
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ])->validate();

        $invoices = DB::table('invoices')->where('outlet_id', $outlet->id);
        $this->dates($invoices, 'created_at', $from, $to);
        $invoiceIds = (clone $invoices)->pluck('id');
        $sales = DB::table('sales')->whereIn('invoice_id', $invoiceIds);
        $returns = DB::table('return_lines')->whereIn('invoice_id', $invoiceIds);
        $refunds = DB::table('pos_refund_allocations')->whereIn('invoice_id', $invoiceIds);
        $tenders = DB::table('pos_tender_allocations')->whereIn('invoice_id', $invoiceIds);

        return [
            'outlet_id' => $outlet->public_id, 'from' => $from, 'to' => $to,
            'sales' => $this->sales($invoices, $sales, $returns, $refunds),
            'payments' => $this->payments($tenders, $invoiceIds),
            'categories' => $this->categories($outlet, $from, $to),
            'activity' => $this->activity($outlet, $from, $to),
        ];
    }

    private function categories(Outlet $outlet, ?string $from, ?string $to): array
    {
        $sales = DB::table('sales as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->where('s.outlet_id', $outlet->id)->groupBy('p.category')
            ->selectRaw('p.category, SUM(s.quantity) units_sold, SUM(s.net_total_price) net_sales, SUM(s.profit) profit');
        $this->dates($sales, 's.created_at', $from, $to);
        $sales = $sales->get()->keyBy('category');
        $inventory = DB::table('products')->where('outlet_id', $outlet->id)->where('isDeleted', false)
            ->groupBy('category')->selectRaw('category, COUNT(*) products, SUM(qty) stock_units, SUM(qty * purchase_price) inventory_cost')->get()->keyBy('category');
        $claims = DB::table('claims as c')->join('products as p', 'p.id', '=', 'c.product_id')
            ->where('c.outlet_id', $outlet->id)->groupBy('p.category')->selectRaw('p.category, COUNT(*) claims');
        $this->dates($claims, 'c.created_at', $from, $to);
        $claims = $claims->get()->keyBy('category');

        return $sales->keys()->merge($inventory->keys())->merge($claims->keys())->unique()->sort()->values()
            ->map(function (string $category) use ($sales, $inventory, $claims) {
                $sale = $sales->get($category);
                $stock = $inventory->get($category);

                return ['category' => $category, 'label' => ucwords(str_replace('_', ' ', $category)),
                    'units_sold' => (int) ($sale->units_sold ?? 0), 'net_sales' => $this->money($sale->net_sales ?? '0.00'),
                    'profit' => $this->money($sale->profit ?? '0.00'), 'products' => (int) ($stock->products ?? 0),
                    'stock_units' => (int) ($stock->stock_units ?? 0), 'inventory_cost' => $this->money($stock->inventory_cost ?? '0.00'),
                    'claims' => (int) ($claims->get($category)?->claims ?? 0)];
            })->all();
    }

    private function sales(Builder $invoices, Builder $sales, Builder $returns, Builder $refunds): array
    {
        $gross = $this->money((clone $invoices)->sum('total_bill'));
        $discounts = $this->money((clone $invoices)->sum('discount'));
        $net = $this->money((clone $invoices)->sum('final_bill'));
        $profit = $this->money((clone $sales)->sum('profit'));
        $returnNet = $this->money((clone $returns)->sum('net_amount'));
        $refundTotal = $this->money((clone $refunds)->sum('amount'));

        return [
            'invoice_count' => (clone $invoices)->count(), 'gross_sales' => $gross, 'discounts' => $discounts,
            'net_sales' => $net, 'gross_profit' => $profit, 'return_net' => $returnNet, 'refunds' => $refundTotal,
            'note' => 'Invoice totals are counted once; tender allocations and settlements are separate measures.',
        ];
    }

    private function payments(Builder $tenders, $invoiceIds): array
    {
        $rows = (clone $tenders)->orderBy('id')->get();
        $method = $rows->groupBy('method')->map(fn ($group) => $this->money($group->sum('amount')))->all();
        $destinations = $rows->groupBy('payment_destination_id')->map(function ($group) {
            $snapshot = $this->json($group->first()->destination_snapshot);

            return ['method' => $group->first()->method, 'destination' => $snapshot['display_name'] ?? 'Historical destination',
                'amount' => $this->money($group->sum('amount'))];
        })->values()->all();
        $settlements = [];
        foreach ($rows as $tender) {
            $event = DB::table('pos_settlement_events')->where('tender_allocation_id', $tender->id)->orderByDesc('sequence')->first();
            if ($event) {
                $settlements[] = $event;
            }
        }
        $fees = $this->money(collect($settlements)->sum('fee_amount'));
        $expected = $this->money(collect($settlements)->sum('expected_net_amount'));
        $received = $this->money(collect($settlements)->sum('received_net_amount'));
        $variance = $this->money(collect($settlements)->sum('variance_amount'));
        $website = DB::table('payments')->join('orders', 'orders.id', '=', 'payments.order_id')
            ->join('invoices', 'invoices.order_id', '=', 'orders.id')->whereIn('invoices.id', $invoiceIds)
            ->where('payments.status', 'paid')->get(['payments.gateway', 'payments.amount']);
        $websiteByGateway = $website->groupBy('gateway')->map(fn ($group) => $this->money($group->sum('amount')))->all();

        return [
            'pos_tender_total' => $this->money($rows->sum('amount')), 'website_payment_total' => $this->money($website->sum('amount')),
            'website_gateway_breakdown' => $websiteByGateway, 'method_breakdown' => $method,
            'destination_breakdown' => $destinations, 'provider_fees' => $fees,
            'expected_settlement' => $expected, 'net_settlement' => $received, 'settlement_variance' => $variance,
            'unreconciled_tenders' => $rows->where('reconciliation_state', 'pending')->count(),
        ];
    }

    private function activity(Outlet $outlet, ?string $from, ?string $to): array
    {
        $definitions = [
            'procurement_orders' => ['purchase_orders', 'outlet_id', 'created_at'], 'stocktakes' => ['stocktake_sessions', 'outlet_id', 'created_at'],
            'transfers_out' => ['stock_transfers', 'source_outlet_id', 'created_at'], 'cash_entries' => ['cash_entries', 'outlet_id', 'created_at'],
            'trade_ins' => ['trade_ins', 'outlet_id', 'created_at'], 'repairs' => ['repair_jobs', 'outlet_id', 'created_at'],
        ];
        $counts = [];
        foreach ($definitions as $key => [$table, $outletColumn, $dateColumn]) {
            $query = DB::table($table)->where($outletColumn, $outlet->id);
            $this->dates($query, $dateColumn, $from, $to);
            $counts[$key] = $query->count();
        }
        $loyalty = DB::table('loyalty_entries')->join('invoices', 'invoices.id', '=', 'loyalty_entries.invoice_id')
            ->where('invoices.outlet_id', $outlet->id);
        $this->dates($loyalty, 'loyalty_entries.created_at', $from, $to);
        $counts['loyalty_entries'] = $loyalty->count();

        $promotion = DB::table('promotion_claims')->leftJoin('invoices', 'invoices.id', '=', 'promotion_claims.invoice_id')
            ->leftJoin('promotions', 'promotions.id', '=', 'promotion_claims.promotion_id')
            ->where(function ($query) use ($outlet) {
                $query->where('invoices.outlet_id', $outlet->id)->orWhere('promotions.outlet_id', $outlet->id);
            });
        $this->dates($promotion, 'promotion_claims.created_at', $from, $to);
        $counts['promotion_claims'] = $promotion->count('promotion_claims.id');

        return $counts;
    }

    private function dates(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from !== null) {
            $query->where($column, '>=', $from.' 00:00:00');
        }
        if ($to !== null) {
            $query->where($column, '<=', $to.' 23:59:59.999999');
        }
    }

    private function money(mixed $value): string
    {
        $value = (string) $value;
        if (! preg_match('/\A-?\d+(?:\.\d{1,2})?\z/', $value)) {
            throw new \LogicException('Report money source is not an exact decimal value.');
        }

        return bcadd($value, '0.00', 2);
    }

    private function json(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return is_array($value) ? $value : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }
}
