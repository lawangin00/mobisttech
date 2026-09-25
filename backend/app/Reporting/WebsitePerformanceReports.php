<?php

namespace App\Reporting;

use App\Identity\Access;
use App\Models\Admin;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Scoped original Website dashboard/report behavior over one authoritative target orders table. */
final class WebsitePerformanceReports
{
    public function filters(array $input): array
    {
        if (array_diff(array_keys($input), ['from', 'to', 'category', 'product', 'outlet'])) {
            throw ValidationException::withMessages(['filter' => 'Unexpected Website report filter.']);
        }
        $data = Validator::make($input, [
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'category' => ['nullable', 'in:mobile_phone,tablet,accessory'],
            'product' => ['nullable', 'uuid'], 'outlet' => ['nullable', 'uuid'],
        ])->validate();
        $today = now()->toDateString();
        $from = $data['from'] ?? now()->subDays(29)->toDateString();
        $to = $data['to'] ?? $today;
        abort_if(Carbon::parse($from)->gt(Carbon::parse($to)) || Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 366, 422,
            'Website reporting accepts an inclusive range of up to 366 days.');

        return ['from' => $from, 'to' => $to, 'category' => $data['category'] ?? null,
            'product' => $data['product'] ?? null, 'outlet' => $data['outlet'] ?? null];
    }

    public function authorize(Admin $actor): void
    {
        $access = app(Access::class);
        abort_unless($access->allows($actor, 'website.orders.manage')
            && $access->allows($actor, 'website.conversions.view'), 403);
    }

    public function orders(Admin $actor, array $filters): Builder
    {
        $this->authorize($actor);
        $query = DB::table('orders as o')->whereIn('o.order_type', ['commerce', 'digital'])
            ->whereDate('o.created_at', '>=', $filters['from'])->whereDate('o.created_at', '<=', $filters['to']);
        if ($filters['category'] || $filters['product'] || $filters['outlet']) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->selectRaw('1')->from('order_items as oi')->leftJoin('products as p', 'p.id', '=', 'oi.product_id')
                    ->leftJoin('outlets as outlet', 'outlet.id', '=', 'oi.outlet_id')
                    ->whereColumn('oi.order_id', 'o.id');
                if ($filters['category']) {
                    $sub->where('p.category', $filters['category']);
                }
                if ($filters['product']) {
                    $sub->where('p.public_id', $filters['product']);
                }
                if ($filters['outlet']) {
                    $sub->where('outlet.public_id', $filters['outlet']);
                }
            });
        }

        return $query;
    }

    public function summary(Admin $actor, array $input): array
    {
        $filters = $this->filters($input);
        $orders = $this->orders($actor, $filters);
        $total = (clone $orders)->count();
        $paid = (clone $orders)->where('o.payment_status', 'paid');
        $paidCount = (clone $paid)->count();
        $commerce = $this->money((clone $paid)->where('o.order_type', 'commerce')->sum('o.total'));
        $digital = $this->money((clone $paid)->where('o.order_type', 'digital')->sum('o.total'));
        $revenue = bcadd($commerce, $digital, 2);
        $ordersByDay = (clone $orders)->selectRaw('DATE(o.created_at) as day, COUNT(*) as orders, SUM(CASE WHEN o.payment_status = ? THEN o.total ELSE 0 END) as paid_total', ['paid'])
            ->whereDate('o.created_at', '>=', now()->subDays(13)->toDateString())->groupByRaw('DATE(o.created_at)')->get()->keyBy('day');
        $series = [];
        for ($n = 13; $n >= 0; $n--) {
            $day = now()->subDays($n)->toDateString();
            $series[] = ['day' => $day, 'orders' => (int) ($ordersByDay->get($day)?->orders ?? 0),
                'paid_order_total' => $this->money($ordersByDay->get($day)?->paid_total ?? '0.00')];
        }
        $items = DB::table('order_items as i')->joinSub((clone $paid)->select('o.id'), 'eligible', 'eligible.id', '=', 'i.order_id')
            ->where('i.item_type', 'product')->groupBy('i.product_listing_id', 'i.title')
            ->orderByDesc('units')->limit(5)
            ->selectRaw('i.title, SUM(i.quantity) as units, SUM(i.line_total) as line_total')
            ->get()->map(fn ($item) => ['title' => $item->title, 'units' => (int) $item->units,
                'line_total_before_order_discounts' => $this->money($item->line_total)])->all();
        $counts = (clone $orders)->selectRaw('o.payment_status, COUNT(*) as quantity')
            ->groupBy('o.payment_status')->pluck('quantity', 'payment_status')->map(fn ($value) => (int) $value)->all();
        $types = (clone $orders)->selectRaw('o.order_type, COUNT(*) as quantity')
            ->groupBy('o.order_type')->pluck('quantity', 'order_type')->map(fn ($value) => (int) $value)->all();
        $requests = DB::table('service_requests')->whereDate('created_at', '>=', $filters['from'])->whereDate('created_at', '<=', $filters['to']);
        $quotes = DB::table('project_quotes')->whereDate('created_at', '>=', $filters['from'])->whereDate('created_at', '<=', $filters['to']);
        $quoteCount = (clone $quotes)->count();
        $paidQuotes = (clone $quotes)->whereNotNull('paid_at')->count();
        $latestAttempts = DB::table('payments as payment')->joinSub(
            DB::table('payments')->selectRaw('order_id, MAX(id) as payment_id')->groupBy('order_id'),
            'latest', 'latest.payment_id', '=', 'payment.id')->select('payment.order_id', 'payment.gateway');
        $gateways = DB::query()->fromSub(
            (clone $orders)->select('o.id')->leftJoinSub($latestAttempts, 'attempt', 'attempt.order_id', '=', 'o.id')
                ->select('o.id', 'attempt.gateway'), 'gateway_orders')
            ->selectRaw('COALESCE(gateway, ?) as gateway, COUNT(*) as quantity', ['none'])
            ->groupBy('gateway')->pluck('quantity', 'gateway')->map(fn ($value) => (int) $value)->all();

        return ['filters' => $filters, 'orders' => $total, 'paid_orders' => $paidCount,
            'pending_or_unpaid_orders' => (clone $orders)->where('o.payment_status', 'unpaid')->count(),
            'paid_reconciliation_orders' => (clone $orders)->where('o.payment_status', 'paid_reconciliation')->count(),
            'paid_commerce_order_total' => $commerce, 'paid_digital_order_total' => $digital,
            'paid_order_total' => $revenue, 'average_paid_order_total' => $paidCount ? bcdiv($revenue, (string) $paidCount, 2) : '0.00',
            'paid_order_percentage' => $total ? bcdiv(bcmul((string) $paidCount, '100', 2), (string) $total, 2) : '0.00',
            'digital_paid_order_share' => bccomp($revenue, '0.00', 2) === 1 ? bcdiv(bcmul($digital, '100', 2), $revenue, 2) : '0.00',
            'returned_orders' => (clone $orders)->where('o.fulfillment_status', 'returned')->count(),
            'refunded_orders' => (clone $orders)->whereIn('o.refund_status', ['partial', 'refunded'])->count(),
            'service_requests' => (clone $requests)->count(), 'new_requests' => (clone $requests)->where('status', 'new')->count(),
            'quotes' => $quoteCount, 'paid_quotes' => $paidQuotes,
            'approved_unpaid_quotes' => (clone $quotes)->where('status', 'approved')->whereNull('paid_at')->count(),
            'quote_paid_percentage' => $quoteCount ? bcdiv(bcmul((string) $paidQuotes, '100', 2), (string) $quoteCount, 2) : '0.00',
            'recent_orders' => (clone $orders)->orderByDesc('o.id')->limit(8)->get(['o.order_number', 'o.order_type', 'o.payment_status', 'o.created_at'])->map(fn ($row) => (array) $row)->all(),
            'recent_requests' => (clone $requests)->orderByDesc('id')->limit(6)->get(['reference', 'status', 'created_at'])->map(fn ($row) => (array) $row)->all(),
            'gateway_attempt_count' => $gateways,
            'payment_status_count' => $counts, 'order_type_count' => $types,
            'top_products' => $items, 'daily' => $series,
            'definition' => 'Paid order face totals only, not net settled revenue, captured payments or profit. Reconciliation-held orders are excluded; item totals do not allocate order discounts.'];
    }

    public function csv(Admin $actor, array $input): array
    {
        $filters = $this->filters($input);
        $orders = $this->orders($actor, $filters);
        $count = (clone $orders)->count();
        abort_if($count > 5000, 422, 'The Website export exceeds 5,000 orders. Use a narrower date or product/outlet range.');
        $headers = ['Order', 'Type', 'Customer', 'Mobile', 'Outlet', 'Total PKR', 'Payment', 'Order Status', 'Refund', 'Created'];
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $headers);
        foreach ($orders->orderBy('o.id')->get(['o.id', 'o.order_number', 'o.order_type', 'o.customer_name', 'o.customer_mobile',
            'o.total', 'o.payment_status', 'o.fulfillment_status', 'o.refund_status', 'o.created_at']) as $order) {
            $outlets = DB::table('order_items')->where('order_id', $order->id)->whereNotNull('outlet_name')
                ->distinct()->orderBy('outlet_name')->pluck('outlet_name')->implode(' | ');
            fputcsv($handle, array_map($this->cell(...), [$order->order_number, $order->order_type,
                $order->customer_name, $order->customer_mobile, $outlets, $order->total, $order->payment_status,
                $order->fulfillment_status, $order->refund_status, $order->created_at]));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return ['filename' => 'website-performance-'.now()->format('Ymd-His').'.csv', 'csv' => $csv, 'count' => $count,
            'filters' => $filters];
    }

    private function money(mixed $raw): string
    {
        return bcadd('0.00', (string) ($raw ?? '0.00'), 2);
    }

    private function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/\A[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }
}
