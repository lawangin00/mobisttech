<?php

namespace App\Commerce;

use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class WebsiteCommerceAdministration
{
    private const FULFILLMENT_TRANSITIONS = [
        'pending' => ['processing'],
        'processing' => ['ready'],
        'ready' => ['dispatched'],
        'dispatched' => ['completed'],
    ];

    public function orders(Admin $actor, array $input = []): array
    {
        $this->authorize($actor);
        $data = Validator::make($input, [
            'status' => ['nullable', 'string', 'in:pending,processing,ready,dispatched,completed,cancelled'],
            'query' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();
        $query = DB::table('orders')->where('order_type', 'commerce');
        if ($data['status'] ?? null) {
            $query->where('fulfillment_status', $data['status']);
        }
        if ($term = trim($data['query'] ?? '')) {
            $query->where(function ($builder) use ($term) {
                $builder->where('order_number', 'like', '%'.$term.'%')
                    ->orWhere('customer_name', 'like', '%'.$term.'%')
                    ->orWhere('customer_email', 'like', '%'.$term.'%');
            });
        }

        return $query->orderByDesc('id')->limit((int) ($data['limit'] ?? 50))->get()->map(fn ($order) => $this->orderPayload($order))->all();
    }

    public function updateOrder(Admin $actor, string $publicId, array $input): array
    {
        $this->authorize($actor);
        $data = Validator::make($input, [
            'version' => ['required', 'integer', 'min:1'],
            'fulfillment_status' => ['required', 'string', 'in:processing,ready,dispatched,completed'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $publicId, $data) {
            $order = DB::table('orders')->where('public_id', $publicId)->where('order_type', 'commerce')->lockForUpdate()->firstOrFail();
            abort_if((int) $order->version !== (int) $data['version'], 409, 'Order changed. Refresh before updating.');
            $allowed = self::FULFILLMENT_TRANSITIONS[$order->fulfillment_status] ?? [];
            if (! in_array($data['fulfillment_status'], $allowed, true)) {
                throw ValidationException::withMessages(['fulfillment_status' => 'The requested order transition is not allowed.']);
            }
            $target = $data['fulfillment_status'];
            $changes = ['fulfillment_status' => $target, 'admin_notes' => $data['admin_notes'] ?? $order->admin_notes,
                'version' => $order->version + 1, 'updated_at' => now(), $target.'_at' => now()];
            if ($target === 'completed') {
                $changes['status'] = 'completed';
            }
            DB::table('orders')->where('id', $order->id)->update($changes);
            IdentityAudit::record('admin', $actor->id, 'website_order_'.$target, 'order:'.$order->order_number);

            return $this->orderPayload(DB::table('orders')->where('id', $order->id)->firstOrFail());
        }, 3);
    }

    public function ordersCsv(Admin $actor, array $input = []): array
    {
        $rows = $this->orders($actor, [...$input, 'limit' => 100]);
        $lines = [['order_number', 'customer_name', 'customer_email', 'total', 'currency', 'order_status', 'payment_status', 'fulfillment_status', 'created_at']];
        foreach ($rows as $row) {
            $lines[] = array_map($this->csvCell(...), [$row['order_number'], $row['customer_name'], $row['customer_email'],
                $row['total'], $row['currency'], $row['status'], $row['payment_status'], $row['fulfillment_status'], $row['created_at']]);
        }
        $csv = collect($lines)->map(fn ($line) => implode(',', array_map(fn ($value) => $this->quoteCsv((string) $value), $line)))->implode("\r\n")."\r\n";

        return ['filename' => 'website-orders-'.now()->format('Ymd-His').'.csv', 'csv' => $csv, 'count' => count($rows)];
    }

    public function reviews(Admin $actor, string $status = 'pending'): array
    {
        $this->authorize($actor);
        Validator::make(['status' => $status], ['status' => ['required', 'in:pending,approved,rejected']])->validate();

        return DB::table('product_reviews as r')->join('product_listings as l', 'l.id', '=', 'r.product_listing_id')
            ->join('order_items as i', 'i.id', '=', 'r.order_item_id')->join('orders as o', 'o.id', '=', 'i.order_id')
            ->where('r.status', $status)->orderBy('r.id')->limit(100)
            ->get(['r.id', 'r.rating', 'r.title', 'r.body', 'r.status', 'r.admin_reply', 'r.approved_at', 'r.created_at',
                'l.slug as product_slug', 'l.name as product_name', 'o.order_number', 'o.customer_name'])
            ->map(fn ($row) => (array) $row)->all();
    }

    public function moderateReview(Admin $actor, int $reviewId, array $input): array
    {
        $this->authorize($actor);
        $data = Validator::make($input, [
            'decision' => ['required', 'in:approved,rejected'],
            'admin_reply' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($actor, $reviewId, $data) {
            $review = DB::table('product_reviews')->where('id', $reviewId)->lockForUpdate()->firstOrFail();
            abort_unless($review->status === 'pending', 409, 'Moderated review history is immutable.');
            DB::table('product_reviews')->where('id', $review->id)->update([
                'status' => $data['decision'], 'admin_reply' => $data['admin_reply'] ?? null,
                'approved_at' => $data['decision'] === 'approved' ? now() : null, 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $actor->id, 'product_review_'.$data['decision'], 'review:'.$review->id);

            return (array) DB::table('product_reviews')->where('id', $review->id)->firstOrFail();
        }, 3);
    }

    private function authorize(Admin $actor): void
    {
        abort_unless(app(Access::class)->allows($actor, 'website.orders.manage'), 403);
    }

    private function orderPayload(object $order): array
    {
        return ['id' => $order->public_id, 'order_number' => $order->order_number, 'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email, 'total' => (string) $order->total, 'currency' => $order->currency,
            'status' => $order->status, 'payment_status' => $order->payment_status, 'fulfillment_status' => $order->fulfillment_status,
            'admin_notes' => $order->admin_notes, 'invoice_number' => $order->invoice_number, 'version' => (int) $order->version,
            'created_at' => $order->created_at, 'updated_at' => $order->updated_at];
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/\A[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function quoteCsv(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
