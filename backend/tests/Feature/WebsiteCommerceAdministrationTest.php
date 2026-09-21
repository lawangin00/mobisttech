<?php

namespace Tests\Feature;

use App\Commerce\WebsiteCommerceAdministration;
use App\Models\Admin;
use App\Models\CustomerAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class WebsiteCommerceAdministrationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_order_status_csv_and_review_moderation_are_permissioned_bounded_and_audited(): void
    {
        $actor = $this->actor;
        $denied = $this->admin([]);
        $product = $this->product();
        $customer = new CustomerAccount;
        $customer->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'W03 Customer',
            'email' => 'customer-'.Str::uuid().'@example.invalid', 'password' => 'SyntheticPass123!', 'is_admin' => false])->save();
        $orderId = DB::table('orders')->insertGetId($this->order());
        $listingId = DB::table('product_listings')->insertGetId([
            'external_source' => 'pos', 'slug' => 'w03-product', 'name' => 'W03 Product', 'category' => 'accessory',
            'is_online' => true, 'public_id' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'item_type' => 'accessory', 'title' => 'W03 Product',
            'quantity' => 1, 'unit_price' => '150.00', 'line_total' => '150.00', 'product_id' => $product->id,
            'outlet_id' => $this->outlet->id]);
        $reviewId = DB::table('product_reviews')->insertGetId(['product_listing_id' => $listingId, 'user_id' => $customer->id,
            'order_item_id' => $itemId, 'rating' => 5, 'title' => 'Good', 'body' => 'Purchased review', 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now()]);
        $service = app(WebsiteCommerceAdministration::class);

        $this->forbidden(fn () => $service->orders($denied));
        $this->assertSame('=Formula Customer', $service->orders($actor)[0]['customer_name']);
        $csv = $service->ordersCsv($actor);
        $this->assertStringContainsString("'=Formula Customer", $csv['csv']);
        $this->assertSame(1, $csv['count']);

        $order = $service->orders($actor)[0];
        foreach (['processing', 'ready', 'dispatched', 'completed'] as $status) {
            $order = $service->updateOrder($actor, $order['id'], ['version' => $order['version'],
                'fulfillment_status' => $status, 'admin_notes' => 'Handled safely']);
            $this->assertSame($status, $order['fulfillment_status']);
        }
        $this->assertSame('completed', $order['status']);
        $this->assertSame(4, DB::table('identity_audit_events')->where('reference', 'order:W03-ORDER')->count());
        try {
            $service->updateOrder($actor, $order['id'], ['version' => 1, 'fulfillment_status' => 'processing']);
            $this->fail('Stale order update succeeded.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }

        $this->assertSame($reviewId, $service->reviews($actor)[0]['id']);
        $review = $service->moderateReview($actor, $reviewId, ['decision' => 'approved', 'admin_reply' => 'Thank you.']);
        $this->assertSame('approved', $review['status']);
        $this->assertNotNull($review['approved_at']);
        $this->assertSame(1, DB::table('identity_audit_events')->where('reference', 'review:'.$reviewId)->count());
        try {
            $service->moderateReview($actor, $reviewId, ['decision' => 'rejected']);
            $this->fail('Moderated review changed twice.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
    }

    private function admin(array $permissions): Admin
    {
        $admin = new Admin;
        $admin->forceFill(['name' => 'W03 Admin', 'email' => Str::uuid().'@example.invalid', 'password' => 'SyntheticPass123!',
            'permissions' => $permissions, 'auth_version' => 1])->save();

        return $admin;
    }

    private function order(): array
    {
        return ['order_number' => 'W03-ORDER', 'order_type' => 'commerce', 'status' => 'confirmed',
            'customer_name' => '=Formula Customer', 'customer_mobile' => '03000000000', 'customer_email' => 'w03@example.invalid',
            'subtotal' => '150.00', 'total' => '150.00', 'currency' => 'PKR', 'payment_status' => 'paid',
            'fulfillment_status' => 'pending', 'public_id' => (string) Str::uuid(), 'version' => 1,
            'created_at' => now(), 'updated_at' => now()];
    }

    private function forbidden(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Permission denial was bypassed.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }
}
