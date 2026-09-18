<?php

namespace App\Commerce;

use App\Addendum\WebsiteCapabilities;
use App\Models\CustomerAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ProductReviews
{
    public function __construct(private WebsiteCapabilities $capabilities) {}

    public function submit(CustomerAccount $customer, array $input): array
    {
        $this->capabilities->assertHistoricalAllowed('order.status');
        $data = Validator::make($input, [
            'order_id' => 'required|uuid',
            'product_id' => 'required|uuid',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:160',
            'body' => 'required|string|min:2|max:5000',
        ])->validate();

        return DB::transaction(function () use ($customer, $data) {
            $this->capabilities->assertCreationAllowed('review.write');
            $order = DB::table('orders')->where('public_id', $data['order_id'])
                ->where('user_id', $customer->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($order->payment_status, ['paid', 'paid_reconciliation'], true)
                || $order->fulfillment_status === 'completed', 409, 'Only a fulfilled or paid order may be reviewed.');
            $product = DB::table('products')->where('public_id', $data['product_id'])->firstOrFail();
            $item = DB::table('order_items')->where('order_id', $order->id)->where('product_id', $product->id)
                ->orderBy('id')->lockForUpdate()->first();
            if (! $item) {
                throw ValidationException::withMessages(['product_id' => 'The product was not purchased on this order.']);
            }
            $listing = DB::table('product_listings')->where('product_id', $product->id)->firstOrFail();
            $existing = DB::table('product_reviews')->where('user_id', $customer->id)
                ->where('order_item_id', $item->id)->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->status === 'pending', 409, 'Published or rejected review history is immutable.');
                DB::table('product_reviews')->where('id', $existing->id)->update([
                    'rating' => $data['rating'], 'title' => $data['title'] ?? null,
                    'body' => trim($data['body']), 'updated_at' => now(),
                ]);
                $id = $existing->id;
            } else {
                $id = DB::table('product_reviews')->insertGetId([
                    'product_listing_id' => $listing->id, 'user_id' => $customer->id, 'order_item_id' => $item->id,
                    'rating' => $data['rating'], 'title' => $data['title'] ?? null, 'body' => trim($data['body']),
                    'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $this->payload((int) $id);
        }, 2);
    }

    public function eligible(CustomerAccount $customer): array
    {
        $this->capabilities->assertHistoricalAllowed('order.status');

        return DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->join('products as p', 'p.id', '=', 'i.product_id')
            ->leftJoin('product_reviews as r', function ($join) use ($customer) {
                $join->on('r.order_item_id', '=', 'i.id')->where('r.user_id', '=', $customer->id);
            })
            ->where('o.user_id', $customer->id)
            ->whereNotNull('i.product_id')
            ->where(function ($query) {
                $query->whereIn('o.payment_status', ['paid', 'paid_reconciliation'])
                    ->orWhere('o.fulfillment_status', 'completed');
            })
            ->orderByDesc('o.id')->orderBy('i.id')->limit(100)
            ->get(['o.public_id as order_id', 'o.order_number', 'p.public_id as product_id', 'p.name as product_name',
                'r.id as review_id', 'r.status as review_status'])
            ->map(fn ($row) => [
                'order_id' => $row->order_id, 'order_number' => $row->order_number,
                'product_id' => $row->product_id, 'product_name' => $row->product_name,
                'review_id' => $row->review_id ? (int) $row->review_id : null,
                'review_status' => $row->review_status,
            ])->all();
    }

    public function mine(CustomerAccount $customer): array
    {
        return DB::table('product_reviews as r')->join('product_listings as l', 'l.id', '=', 'r.product_listing_id')
            ->where('r.user_id', $customer->id)->orderByDesc('r.id')->limit(50)
            ->get(['r.id', 'l.slug', 'r.rating', 'r.title', 'r.body', 'r.status', 'r.created_at', 'r.updated_at'])
            ->map(fn ($row) => ['id' => (int) $row->id, 'product_slug' => $row->slug, 'rating' => (int) $row->rating,
                'title' => $row->title, 'body' => $row->body, 'status' => $row->status,
                'created_at' => $row->created_at, 'updated_at' => $row->updated_at])->all();
    }

    private function payload(int $id): array
    {
        $row = DB::table('product_reviews as r')->join('product_listings as l', 'l.id', '=', 'r.product_listing_id')
            ->where('r.id', $id)->select('r.*', 'l.slug')->firstOrFail();

        return ['id' => (int) $row->id, 'product_slug' => $row->slug, 'rating' => (int) $row->rating,
            'title' => $row->title, 'body' => $row->body, 'status' => $row->status,
            'created_at' => $row->created_at, 'updated_at' => $row->updated_at];
    }
}
