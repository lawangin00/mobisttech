<?php

namespace App\Engagement;

use App\Addendum\WebsiteCapabilities;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Inventory\StockLedger;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use LogicException;
use Throwable;

final class CustomerEngagement
{
    private const EVENTS = ['back_in_stock', 'price_drop'];

    public function __construct(
        private readonly WebsiteCapabilities $capabilities,
        private readonly StockLedger $stock,
        private readonly Access $access,
        private readonly EngagementDeliveryGateway $gateway,
    ) {}

    public function newGuestToken(): string
    {
        return Str::random(64);
    }

    public function saveForLater(?CustomerAccount $customer, ?string $guestToken, string $productPublicId): array
    {
        return DB::transaction(function () use ($customer, $guestToken, $productPublicId) {
            $this->capabilities->assertCreationAllowed('wishlist.write');
            $owner = $this->owner($customer, $guestToken);
            $product = Product::where('public_id', $productPublicId)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
            $price = SourceRow::money((string) $product->sale_price);
            $existing = DB::table('wishlist_items')->where('owner_scope_hash', $owner['scope'])
                ->where('product_id', $product->id)->lockForUpdate()->first();
            if ($existing) {
                DB::table('wishlist_items')->where('id', $existing->id)->update([
                    'product_name_snapshot' => $product->name, 'price_snapshot' => $price, 'updated_at' => now(),
                ]);

                return $this->wishlistItem((int) $existing->id);
            }
            $id = (int) DB::table('wishlist_items')->insertGetId([
                'public_id' => (string) Str::uuid(), 'owner_scope_hash' => $owner['scope'],
                'customer_account_id' => $owner['customer_id'], 'guest_owner_hash' => $owner['guest_hash'],
                'product_id' => $product->id, 'product_name_snapshot' => $product->name, 'price_snapshot' => $price,
                'added_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $this->wishlistItem($id);
        });
    }

    public function removeSaved(?CustomerAccount $customer, ?string $guestToken, string $productPublicId): array
    {
        $owner = $this->owner($customer, $guestToken);
        $productId = Product::where('public_id', $productPublicId)->value('id');
        $removed = $productId ? DB::table('wishlist_items')->where('owner_scope_hash', $owner['scope'])
            ->where('product_id', $productId)->delete() : 0;

        return ['removed' => $removed > 0];
    }

    public function wishlist(?CustomerAccount $customer, ?string $guestToken): array
    {
        if (! $this->capabilities->allowsScope('commerce')) {
            throw new LogicException('Wishlist discovery is inactive in the published Website mode.');
        }
        $owner = $this->owner($customer, $guestToken);
        $ids = DB::table('wishlist_items')->where('owner_scope_hash', $owner['scope'])->orderBy('id')->pluck('id');

        return DB::transaction(fn () => $ids->map(fn ($id) => $this->wishlistItem((int) $id))->all());
    }

    public function claimGuestWishlist(CustomerAccount $customer, string $guestToken): array
    {
        $this->assertCustomer($customer);

        return DB::transaction(function () use ($customer, $guestToken) {
            $this->capabilities->assertCreationAllowed('wishlist.write');
            $guest = $this->owner(null, $guestToken);
            $account = $this->owner($customer, null);
            $items = DB::table('wishlist_items')->where('owner_scope_hash', $guest['scope'])->orderBy('id')->lockForUpdate()->get();
            foreach ($items as $item) {
                $existing = DB::table('wishlist_items')->where('owner_scope_hash', $account['scope'])
                    ->where('product_id', $item->product_id)->lockForUpdate()->first();
                if ($existing) {
                    DB::table('wishlist_items')->where('id', $item->id)->delete();

                    continue;
                }
                DB::table('wishlist_items')->where('id', $item->id)->update([
                    'owner_scope_hash' => $account['scope'], 'customer_account_id' => $customer->id,
                    'guest_owner_hash' => null, 'updated_at' => now(),
                ]);
            }

            return $this->wishlist($customer, null);
        });
    }

    public function subscribe(CustomerAccount $customer, string $productPublicId, array $input): array
    {
        $this->assertCustomer($customer, true);
        $this->fields($input, ['events', 'consent']);
        $data = Validator::make($input, [
            'events' => 'required|array|min:1|max:2', 'events.*' => 'required|string|in:'.implode(',', self::EVENTS),
            'consent' => 'required|accepted',
        ])->validate();
        $events = array_values(array_unique($data['events']));

        return DB::transaction(function () use ($customer, $productPublicId, $events) {
            $this->capabilities->assertCreationAllowed('product-notification.subscribe');
            $product = Product::where('public_id', $productPublicId)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
            $stock = $this->stock->snapshot($product->id);
            $price = SourceRow::money((string) $product->sale_price);
            $this->ensurePreference($customer->id);
            $result = [];
            foreach ($events as $event) {
                $row = DB::table('product_notification_subscriptions')->where('customer_account_id', $customer->id)
                    ->where('product_id', $product->id)->where('event_type', $event)->where('channel', 'email')->lockForUpdate()->first();
                if ($row) {
                    DB::table('product_notification_subscriptions')->where('id', $row->id)->update([
                        'active' => true, 'baseline_price' => $price, 'last_observed_price' => $price,
                        'last_observed_available' => $stock['available'], 'consented_at' => now(), 'unsubscribed_at' => null,
                        'updated_at' => now(),
                    ]);
                    $id = (int) $row->id;
                } else {
                    $id = (int) DB::table('product_notification_subscriptions')->insertGetId([
                        'public_id' => (string) Str::uuid(), 'customer_account_id' => $customer->id, 'product_id' => $product->id,
                        'event_type' => $event, 'channel' => 'email', 'active' => true,
                        'baseline_price' => $price, 'last_observed_price' => $price,
                        'last_observed_available' => $stock['available'], 'consented_at' => now(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $result[] = $this->subscriptionPayload($id, true);
            }

            return $result;
        });
    }

    public function preferences(CustomerAccount $customer): array
    {
        $this->assertCustomer($customer);
        $this->ensurePreference($customer->id);
        $row = DB::table('notification_preferences')->where('customer_account_id', $customer->id)->firstOrFail();

        return ['email_enabled' => (bool) $row->email_enabled, 'max_per_hour' => (int) $row->max_per_hour, 'version' => (int) $row->version];
    }

    public function updatePreferences(CustomerAccount $customer, array $input): array
    {
        $this->assertCustomer($customer);
        $this->fields($input, ['version', 'email_enabled', 'max_per_hour']);
        $data = Validator::make($input, [
            'version' => 'required|integer|min:1', 'email_enabled' => 'required|boolean', 'max_per_hour' => 'required|integer|min:1|max:10',
        ])->validate();

        return DB::transaction(function () use ($customer, $data) {
            $this->ensurePreference($customer->id);
            $row = DB::table('notification_preferences')->where('customer_account_id', $customer->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $row->version === (int) $data['version'], 409, 'Notification preference version changed.');
            DB::table('notification_preferences')->where('customer_account_id', $customer->id)->update([
                'email_enabled' => (bool) $data['email_enabled'], 'max_per_hour' => (int) $data['max_per_hour'],
                'version' => (int) $row->version + 1, 'updated_at' => now(),
            ]);

            return $this->preferences($customer);
        });
    }

    public function unsubscribe(CustomerAccount $customer, string $productPublicId, ?array $events = null): array
    {
        $this->assertCustomer($customer);
        $events ??= self::EVENTS;
        abort_if(array_diff($events, self::EVENTS), 422, 'Unknown notification event.');
        $productId = Product::where('public_id', $productPublicId)->value('id');
        if ($productId) {
            DB::table('product_notification_subscriptions')->where('customer_account_id', $customer->id)
                ->where('product_id', $productId)->whereIn('event_type', $events)->update([
                    'active' => false, 'unsubscribed_at' => now(), 'updated_at' => now(),
                ]);
        }

        return ['unsubscribed' => true];
    }

    public function unsubscribeByToken(string $token): array
    {
        [$publicId, $signature] = array_pad(explode('.', $token, 2), 2, null);
        if (! is_string($publicId) || ! is_string($signature) || ! Str::isUuid($publicId)
            || ! hash_equals($this->unsubscribeSignature($publicId), $signature)) {
            return ['unsubscribed' => true];
        }
        DB::table('product_notification_subscriptions')->where('public_id', $publicId)->update([
            'active' => false, 'unsubscribed_at' => now(), 'updated_at' => now(),
        ]);

        return ['unsubscribed' => true];
    }

    public function subscriptions(CustomerAccount $customer): array
    {
        $this->assertCustomer($customer);
        if (! $this->capabilities->allowsScope('commerce')) {
            throw new LogicException('Product notification discovery is inactive in the published Website mode.');
        }

        return DB::table('product_notification_subscriptions')->where('customer_account_id', $customer->id)
            ->where('active', true)->orderBy('id')->pluck('id')
            ->map(fn ($id) => $this->subscriptionPayload((int) $id, true))->all();
    }

    public function dispatch(Admin $actor, int $limit = 100): array
    {
        $this->authorize($actor, 'website.engagement.manage');
        abort_if($limit < 1 || $limit > 500, 422, 'Engagement dispatch limit must be 1-500.');
        if (! (bool) config('engagement.external_delivery_enabled', false)) {
            return ['enabled' => false, 'attempted' => 0, 'sent' => 0, 'failed' => 0, 'rate_limited' => 0];
        }
        $snapshot = $this->capabilities->snapshot();
        if (! ($snapshot['capabilities']['commerce'] ?? false)) {
            return ['enabled' => true, 'attempted' => 0, 'sent' => 0, 'failed' => 0, 'rate_limited' => 0];
        }
        $ids = DB::table('product_notification_subscriptions as s')->join('notification_preferences as p', 'p.customer_account_id', '=', 's.customer_account_id')
            ->where('s.active', true)->where('p.email_enabled', true)->orderBy('s.id')->limit($limit)->pluck('s.id');
        $result = ['enabled' => true, 'attempted' => 0, 'sent' => 0, 'failed' => 0, 'rate_limited' => 0];
        foreach ($ids as $id) {
            $prepared = $this->prepareDelivery((int) $id);
            if ($prepared === null) {
                continue;
            }
            if (($prepared['rate_limited'] ?? false) === true) {
                $result['rate_limited']++;

                continue;
            }
            $result['attempted']++;
            try {
                $provider = $this->gateway->send($prepared['message']);
                $this->finishDelivery((int) $prepared['attempt_id'], true, (string) ($provider['provider_reference'] ?? ''));
                $result['sent']++;
            } catch (Throwable $error) {
                $this->finishDelivery((int) $prepared['attempt_id'], false, null, class_basename($error));
                $result['failed']++;
            }
        }
        IdentityAudit::record('admin', $actor->id, 'customer_engagement_dispatch', 'attempted:'.$result['attempted']);

        return $result;
    }

    public function summary(Admin $actor): array
    {
        $this->authorize($actor, 'website.engagement.manage');

        return [
            'active_subscriptions' => DB::table('product_notification_subscriptions')->where('active', true)->count(),
            'account_wishlist_items' => DB::table('wishlist_items')->whereNotNull('customer_account_id')->count(),
            'guest_wishlist_items' => DB::table('wishlist_items')->whereNotNull('guest_owner_hash')->count(),
            'sent_deliveries' => DB::table('notification_delivery_attempts')->where('status', 'sent')->count(),
            'failed_deliveries' => DB::table('notification_delivery_attempts')->where('status', 'failed')->count(),
            'external_delivery_enabled' => (bool) config('engagement.external_delivery_enabled', false),
        ];
    }

    private function prepareDelivery(int $subscriptionId): ?array
    {
        return DB::transaction(function () use ($subscriptionId) {
            $subscription = DB::table('product_notification_subscriptions')->where('id', $subscriptionId)->lockForUpdate()->first();
            if (! $subscription || ! $subscription->active) {
                return null;
            }
            $preference = DB::table('notification_preferences')->where('customer_account_id', $subscription->customer_account_id)
                ->lockForUpdate()->first();
            if (! $preference || ! $preference->email_enabled) {
                return null;
            }
            $customer = CustomerAccount::query()->whereKey($subscription->customer_account_id)->first();
            if (! $customer || ! $customer->usable() || ! $customer->email_verified_at || ! filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }
            $mode = $this->capabilities->snapshot(true);
            if (! ($mode['capabilities']['commerce'] ?? false)) {
                return null;
            }
            $product = Product::whereKey($subscription->product_id)->lockForUpdate()->firstOrFail();
            $pending = DB::table('notification_delivery_attempts')->where('subscription_id', $subscription->id)
                ->whereIn('status', ['prepared', 'failed'])->orderBy('id')->lockForUpdate()->first();
            if ($pending && $product->isDeleted) {
                DB::table('notification_delivery_attempts')->where('id', $pending->id)->update([
                    'status' => 'suppressed', 'failure_code' => 'product_unavailable', 'updated_at' => now(),
                ]);
                DB::table('product_notification_subscriptions')->where('id', $subscription->id)->update([
                    'last_observed_price' => SourceRow::money((string) $product->sale_price),
                    'last_observed_available' => 0, 'updated_at' => now(),
                ]);

                return null;
            }
            if ($pending) {
                if (! $this->consumeRate($customer->id, (int) $preference->max_per_hour)) {
                    return ['rate_limited' => true];
                }
                DB::table('notification_delivery_attempts')->where('id', $pending->id)->update([
                    'status' => 'prepared', 'attempt_count' => (int) $pending->attempt_count + 1,
                    'last_attempt_at' => now(), 'failure_code' => null, 'updated_at' => now(),
                ]);

                return $this->preparedPayload($pending, $subscription, $customer);
            }
            $price = SourceRow::money((string) $product->sale_price);
            $stock = $this->stock->snapshot($product->id);
            $available = $product->isDeleted ? 0 : (int) $stock['available'];
            $eligible = $subscription->event_type === 'back_in_stock'
                ? (int) $subscription->last_observed_available === 0 && $available > 0
                : bccomp($price, (string) $subscription->last_observed_price, 2) < 0;
            if (! $eligible) {
                DB::table('product_notification_subscriptions')->where('id', $subscription->id)->update([
                    'last_observed_price' => $price, 'last_observed_available' => $available, 'updated_at' => now(),
                ]);

                return null;
            }
            if (! $this->consumeRate($customer->id, (int) $preference->max_per_hour)) {
                return ['rate_limited' => true];
            }
            $eventKey = hash('sha256', implode('|', [
                $subscription->public_id, $subscription->event_type, $subscription->last_observed_price,
                $subscription->last_observed_available, $price, $available,
            ]));
            $existing = DB::table('notification_delivery_attempts')->where('event_key', $eventKey)->lockForUpdate()->first();
            if ($existing && $existing->status === 'sent') {
                DB::table('product_notification_subscriptions')->where('id', $subscription->id)->update([
                    'last_observed_price' => $price, 'last_observed_available' => $available, 'updated_at' => now(),
                ]);

                return null;
            }
            $payload = $this->message($subscription, $customer, $product, $price, $available, $eventKey);
            $json = json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $attemptId = (int) DB::table('notification_delivery_attempts')->insertGetId([
                'public_id' => (string) Str::uuid(), 'subscription_id' => $subscription->id, 'event_key' => $eventKey,
                'event_type' => $subscription->event_type, 'price_snapshot' => $price, 'available_snapshot' => $available,
                'status' => 'prepared', 'attempt_count' => 1, 'payload' => $json, 'payload_sha256' => hash('sha256', $json),
                'prepared_at' => now(), 'last_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return ['attempt_id' => $attemptId, 'message' => $payload];
        });
    }

    private function finishDelivery(int $attemptId, bool $success, ?string $providerReference = null, ?string $failureCode = null): void
    {
        DB::transaction(function () use ($attemptId, $success, $providerReference, $failureCode) {
            $attempt = DB::table('notification_delivery_attempts')->where('id', $attemptId)->lockForUpdate()->firstOrFail();
            $subscription = DB::table('product_notification_subscriptions')->where('id', $attempt->subscription_id)->lockForUpdate()->firstOrFail();
            if ($attempt->status === 'sent') {
                return;
            }
            if ($success) {
                DB::table('notification_delivery_attempts')->where('id', $attempt->id)->update([
                    'status' => 'sent', 'provider_reference' => $this->nullable($providerReference), 'failure_code' => null,
                    'sent_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('product_notification_subscriptions')->where('id', $subscription->id)->update([
                    'last_observed_price' => $attempt->price_snapshot,
                    'last_observed_available' => $attempt->available_snapshot,
                    'last_notified_at' => now(), 'updated_at' => now(),
                ]);

                return;
            }
            DB::table('notification_delivery_attempts')->where('id', $attempt->id)->update([
                'status' => 'failed', 'provider_reference' => null,
                'failure_code' => mb_substr((string) $failureCode, 0, 120), 'updated_at' => now(),
            ]);
        });
    }

    private function consumeRate(int $customerId, int $maxPerHour): bool
    {
        $bucket = now()->startOfHour();
        DB::table('notification_rate_buckets')->insertOrIgnore([
            'customer_account_id' => $customerId, 'bucket_start' => $bucket,
            'attempt_count' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $row = DB::table('notification_rate_buckets')->where('customer_account_id', $customerId)
            ->where('bucket_start', $bucket)->lockForUpdate()->firstOrFail();
        if ((int) $row->attempt_count >= $maxPerHour) {
            return false;
        }
        DB::table('notification_rate_buckets')->where('id', $row->id)->update([
            'attempt_count' => (int) $row->attempt_count + 1, 'updated_at' => now(),
        ]);

        return true;
    }

    private function preparedPayload(object $attempt, object $subscription, CustomerAccount $customer): array
    {
        $payload = json_decode($attempt->payload, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals((string) $attempt->payload_sha256, hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))), 500, 'Stored engagement payload integrity check failed.');

        return ['attempt_id' => (int) $attempt->id, 'message' => $payload];
    }

    private function message(object $subscription, CustomerAccount $customer, Product $product, string $price, int $available, string $eventKey): array
    {
        return [
            'idempotency_key' => $eventKey, 'channel' => 'email', 'to' => mb_strtolower($customer->email),
            'event_type' => $subscription->event_type,
            'product' => ['id' => $product->public_id, 'name' => $product->name, 'sale_price' => $price, 'available' => $available],
            'unsubscribe_token' => $this->unsubscribeToken($subscription->public_id),
        ];
    }

    private function wishlistItem(int $id): array
    {
        $item = DB::table('wishlist_items')->where('id', $id)->firstOrFail();
        $product = Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail();
        $available = 0;
        $currentPrice = null;
        $currentName = $item->product_name_snapshot;
        if (! $product->isDeleted) {
            $snapshot = $this->stock->snapshot($product->id);
            $available = (int) $snapshot['available'];
            $currentPrice = SourceRow::money((string) $product->sale_price);
            $currentName = $product->name;
        }

        return [
            'public_id' => $item->public_id, 'product_id' => $product->public_id,
            'name' => $currentName, 'name_snapshot' => $item->product_name_snapshot,
            'price_snapshot' => (string) $item->price_snapshot, 'current_price' => $currentPrice,
            'available' => ! $product->isDeleted && $available > 0, 'available_quantity' => $available,
            'added_at' => $item->added_at,
        ];
    }

    private function subscriptionPayload(int $id, bool $includeToken): array
    {
        $row = DB::table('product_notification_subscriptions as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->where('s.id', $id)->select('s.*', 'p.public_id as product_public_id', 'p.name as product_name', 'p.isDeleted')->firstOrFail();
        $payload = [
            'public_id' => $row->public_id, 'product_id' => $row->product_public_id,
            'product_name' => $row->product_name, 'event_type' => $row->event_type, 'channel' => $row->channel,
            'active' => (bool) $row->active, 'consented_at' => $row->consented_at,
            'last_notified_at' => $row->last_notified_at, 'product_available' => ! (bool) $row->isDeleted,
        ];
        if ($includeToken) {
            $payload['unsubscribe_token'] = $this->unsubscribeToken($row->public_id);
        }

        return $payload;
    }

    private function owner(?CustomerAccount $customer, ?string $guestToken): array
    {
        if ($customer) {
            $this->assertCustomer($customer);
            abort_if($guestToken !== null, 422, 'Account and guest ownership cannot be combined.');

            return ['scope' => hash('sha256', 'account:'.$customer->id), 'customer_id' => $customer->id, 'guest_hash' => null];
        }
        abort_unless(is_string($guestToken) && preg_match('/\A[A-Za-z0-9_-]{32,128}\z/', $guestToken), 422, 'A valid guest owner token is required.');
        $hash = hash('sha256', 'guest:'.$guestToken);

        return ['scope' => $hash, 'customer_id' => null, 'guest_hash' => $hash];
    }

    private function ensurePreference(int $customerId): void
    {
        DB::table('notification_preferences')->insertOrIgnore([
            'customer_account_id' => $customerId, 'email_enabled' => true, 'max_per_hour' => 5,
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function unsubscribeToken(string $publicId): string
    {
        return $publicId.'.'.$this->unsubscribeSignature($publicId);
    }

    private function unsubscribeSignature(string $publicId): string
    {
        $secret = (string) config('engagement.unsubscribe_secret');
        if ($secret === '') {
            throw new LogicException('Engagement unsubscribe secret is unavailable.');
        }

        return hash_hmac('sha256', $publicId, $secret);
    }

    private function assertCustomer(CustomerAccount $customer, bool $verifiedEmail = false): void
    {
        abort_unless($customer->usable(), 404);
        if ($verifiedEmail) {
            abort_unless($customer->email_verified_at && filter_var($customer->email, FILTER_VALIDATE_EMAIL), 422,
                'Verified customer email is required for product notifications.');
        }
    }

    private function authorize(Admin $actor, string $permission): void
    {
        abort_unless($this->access->allows($actor, $permission), 403);
    }

    private function fields(array $input, array $allowed): void
    {
        abort_if(array_diff(array_keys($input), $allowed), 422, 'Unexpected engagement input fields.');
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonical($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }
}
