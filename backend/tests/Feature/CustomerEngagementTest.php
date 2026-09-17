<?php

namespace Tests\Feature;

use App\Cms\WebsiteModePublication;
use App\Engagement\CustomerEngagement;
use App\Engagement\EngagementDeliveryGateway;
use App\Models\CustomerAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class CustomerEngagementTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        config(['engagement.external_delivery_enabled' => false, 'engagement.unsubscribe_secret' => 'synthetic-engagement-secret']);
        $draft = app(WebsiteModePublication::class)->saveDraft($this->actor, 'commerce_only');
        app(WebsiteModePublication::class)->publish($this->actor, $draft['id']);
    }

    public function test_guest_wishlist_claim_unavailable_product_and_mode_isolation(): void
    {
        $product = $this->product();
        $this->acquire($product, 1);
        $service = app(CustomerEngagement::class);
        $guest = $service->newGuestToken();
        $saved = $service->saveForLater(null, $guest, $product->public_id);
        $this->assertTrue($saved['available']);
        $this->assertCount(1, $service->wishlist(null, $guest));

        $customer = $this->customer('wishlist');
        $claimed = $service->claimGuestWishlist($customer, $guest);
        $this->assertCount(1, $claimed);
        $this->assertSame(1, DB::table('wishlist_items')->where('customer_account_id', $customer->id)->count());
        $this->assertSame(0, DB::table('wishlist_items')->whereNotNull('guest_owner_hash')->count());
        $service->saveForLater($customer, null, $product->public_id);
        $this->assertSame(1, DB::table('wishlist_items')->count());

        DB::table('products')->where('id', $product->id)->update(['isDeleted' => true, 'updated_at' => now()]);
        $unavailable = $service->wishlist($customer, null)[0];
        $this->assertFalse($unavailable['available']);
        $this->assertNull($unavailable['current_price']);
        $this->assertSame('200.02', $unavailable['price_snapshot']);

        $modes = app(WebsiteModePublication::class);
        $draft = $modes->saveDraft($this->actor, 'digital_only');
        $modes->publish($this->actor, $draft['id']);
        $this->reject(fn () => $service->wishlist($customer, null));
        $this->assertSame(1, DB::table('wishlist_items')->count());
        $this->assertTrue($service->removeSaved($customer, null, $product->public_id)['removed']);
    }

    public function test_consent_preferences_and_unsubscribe_are_owned_and_idempotent(): void
    {
        $product = $this->product();
        $service = app(CustomerEngagement::class);
        $unverified = $this->customer('unverified', false);
        $this->reject(fn () => $service->subscribe($unverified, $product->public_id, [
            'events' => ['price_drop'], 'consent' => true,
        ]));

        $customer = $this->customer('subscriber');
        $subscriptions = $service->subscribe($customer, $product->public_id, [
            'events' => ['back_in_stock', 'price_drop'], 'consent' => true,
        ]);
        $service->subscribe($customer, $product->public_id, [
            'events' => ['back_in_stock', 'price_drop'], 'consent' => true,
        ]);
        $this->assertSame(2, DB::table('product_notification_subscriptions')->count());
        $preference = $service->preferences($customer);
        $updated = $service->updatePreferences($customer, [
            'version' => $preference['version'], 'email_enabled' => true, 'max_per_hour' => 3,
        ]);
        $this->assertSame(3, $updated['max_per_hour']);

        $token = $subscriptions[0]['unsubscribe_token'];
        $this->assertTrue($service->unsubscribeByToken($token)['unsubscribed']);
        $this->assertSame(1, DB::table('product_notification_subscriptions')->where('active', true)->count());
        $this->assertTrue($service->unsubscribeByToken($token)['unsubscribed']);
        $this->assertTrue($service->unsubscribe($customer, $product->public_id)['unsubscribed']);
        $this->assertSame(0, DB::table('product_notification_subscriptions')->where('active', true)->count());
    }

    public function test_fake_delivery_deduplicates_price_and_stock_events_and_rate_limits(): void
    {
        $product = $this->product();
        $customer = $this->customer('delivery');
        $fake = new FakeEngagementGateway;
        $this->app->instance(EngagementDeliveryGateway::class, $fake);
        config(['engagement.external_delivery_enabled' => true]);
        $service = app(CustomerEngagement::class);
        $service->subscribe($customer, $product->public_id, [
            'events' => ['back_in_stock', 'price_drop'], 'consent' => true,
        ]);
        $pref = $service->preferences($customer);
        $service->updatePreferences($customer, [
            'version' => $pref['version'], 'email_enabled' => true, 'max_per_hour' => 1,
        ]);

        $this->acquire($product, 1);
        $this->setPrice($product->id, '150.00');
        $first = $service->dispatch($this->actor);
        $this->assertSame(1, $first['sent']);
        $this->assertSame(1, $first['rate_limited']);
        $this->assertSame(1, DB::table('notification_delivery_attempts')->count());
        $this->assertCount(1, $fake->messages);

        DB::table('notification_rate_buckets')->delete();
        $second = $service->dispatch($this->actor);
        $this->assertSame(1, $second['sent']);
        $this->assertSame(2, DB::table('notification_delivery_attempts')->where('status', 'sent')->count());
        DB::table('notification_rate_buckets')->delete();
        $third = $service->dispatch($this->actor);
        $this->assertSame(0, $third['attempted']);
    }

    public function test_delivery_defaults_off_retries_same_payload_and_stops_in_inactive_mode(): void
    {
        $product = $this->product();
        $customer = $this->customer('retry');
        $service = app(CustomerEngagement::class);
        $service->subscribe($customer, $product->public_id, ['events' => ['price_drop'], 'consent' => true]);
        $this->setPrice($product->id, '150.00');
        $disabled = $service->dispatch($this->actor);
        $this->assertFalse($disabled['enabled']);
        $this->assertSame(0, DB::table('notification_delivery_attempts')->count());

        $fake = new FakeEngagementGateway;
        $fake->failuresRemaining = 1;
        $this->app->instance(EngagementDeliveryGateway::class, $fake);
        config(['engagement.external_delivery_enabled' => true]);
        $service = app(CustomerEngagement::class);
        $this->assertSame(1, $service->dispatch($this->actor)['failed']);
        $attempt = DB::table('notification_delivery_attempts')->firstOrFail();
        $hash = $attempt->payload_sha256;
        $this->assertSame(1, (int) $attempt->attempt_count);
        DB::table('notification_rate_buckets')->delete();
        $this->assertSame(1, $service->dispatch($this->actor)['sent']);
        $attempt = DB::table('notification_delivery_attempts')->firstOrFail();
        $this->assertSame(2, (int) $attempt->attempt_count);
        $this->assertSame($hash, $attempt->payload_sha256);
        $this->assertEquals($fake->messages[0], $fake->messages[1]);

        $this->setPrice($product->id, '100.00');
        $modes = app(WebsiteModePublication::class);
        $draft = $modes->saveDraft($this->actor, 'digital_only');
        $modes->publish($this->actor, $draft['id']);
        DB::table('notification_rate_buckets')->delete();
        $this->assertSame(0, $service->dispatch($this->actor)['attempted']);
        $this->reject(fn () => $service->subscriptions($customer));
    }

    private function customer(string $name, bool $verified = true): CustomerAccount
    {
        $customer = new CustomerAccount;
        $customer->forceFill([
            'public_id' => (string) Str::uuid(), 'name' => 'Customer '.$name,
            'email' => $name.'-'.Str::uuid().'@example.invalid', 'password' => 'SyntheticPass123!',
            'email_verified_at' => $verified ? now() : null, 'is_admin' => false,
        ])->save();

        return $customer;
    }

    private function setPrice(int $productId, string $price): void
    {
        DB::table('products')->where('id', $productId)->update([
            'price' => $price, 'sale_price' => $price, 'version' => DB::raw('version + 1'), 'updated_at' => now(),
        ]);
    }
}

final class FakeEngagementGateway extends EngagementDeliveryGateway
{
    public int $failuresRemaining = 0;

    public array $messages = [];

    public function send(array $message): array
    {
        $this->messages[] = $message;
        if ($this->failuresRemaining > 0) {
            $this->failuresRemaining--;
            throw new LogicException('Synthetic delivery failure.');
        }

        return ['provider_reference' => 'fake-'.count($this->messages)];
    }
}
