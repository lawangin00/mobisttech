<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('customer_account_id')->primary()->constrained('users')->restrictOnDelete();
            $table->boolean('email_enabled')->default(true);
            $table->unsignedTinyInteger('max_per_hour')->default(5);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps(6);
        });
        DB::statement('ALTER TABLE notification_preferences ADD CONSTRAINT mt39_pref_rate CHECK (max_per_hour BETWEEN 1 AND 10)');

        Schema::create('product_notification_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('customer_account_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->enum('event_type', ['back_in_stock', 'price_drop']);
            $table->enum('channel', ['email'])->default('email');
            $table->boolean('active')->default(true);
            $table->decimal('baseline_price', 19, 2);
            $table->decimal('last_observed_price', 19, 2);
            $table->unsignedInteger('last_observed_available')->default(0);
            $table->dateTime('consented_at', 6);
            $table->dateTime('unsubscribed_at', 6)->nullable();
            $table->dateTime('last_notified_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['customer_account_id', 'product_id', 'event_type', 'channel'], 'mt39_subscription_owner_product_event');
            $table->index(['active', 'event_type', 'updated_at'], 'mt39_subscription_dispatch');
        });
        DB::statement('ALTER TABLE product_notification_subscriptions ADD CONSTRAINT mt39_subscription_money CHECK (baseline_price >= 0 AND last_observed_price >= 0)');

        Schema::create('notification_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('subscription_id')->constrained('product_notification_subscriptions')->restrictOnDelete();
            $table->char('event_key', 64)->collation('utf8mb4_bin')->unique();
            $table->enum('event_type', ['back_in_stock', 'price_drop']);
            $table->decimal('price_snapshot', 19, 2);
            $table->unsignedInteger('available_snapshot');
            $table->enum('status', ['prepared', 'sent', 'failed', 'suppressed'])->default('prepared');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->json('payload');
            $table->char('payload_sha256', 64)->collation('utf8mb4_bin');
            $table->string('provider_reference', 190)->nullable();
            $table->string('failure_code', 120)->nullable();
            $table->dateTime('prepared_at', 6);
            $table->dateTime('last_attempt_at', 6)->nullable();
            $table->dateTime('sent_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['subscription_id', 'status', 'created_at'], 'mt39_delivery_subscription_status');
        });
        DB::statement('ALTER TABLE notification_delivery_attempts ADD CONSTRAINT mt39_delivery_money CHECK (price_snapshot >= 0)');

        Schema::create('notification_rate_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_account_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('bucket_start', 6);
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamps(6);
            $table->unique(['customer_account_id', 'bucket_start'], 'mt39_rate_customer_bucket');
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->char('owner_scope_hash', 64)->collation('utf8mb4_bin');
            $table->foreignId('customer_account_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->char('guest_owner_hash', 64)->collation('utf8mb4_bin')->nullable();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->decimal('price_snapshot', 19, 2);
            $table->dateTime('added_at', 6);
            $table->timestamps(6);
            $table->unique(['owner_scope_hash', 'product_id'], 'mt39_wishlist_owner_product');
            $table->index(['customer_account_id', 'updated_at'], 'mt39_wishlist_customer');
            $table->index(['guest_owner_hash', 'updated_at'], 'mt39_wishlist_guest');
        });
        DB::statement('ALTER TABLE wishlist_items ADD CONSTRAINT mt39_wishlist_owner CHECK ((customer_account_id IS NULL) <> (guest_owner_hash IS NULL))');
        DB::statement('ALTER TABLE wishlist_items ADD CONSTRAINT mt39_wishlist_money CHECK (price_snapshot >= 0)');
    }

    public function down(): void
    {
        foreach (['notification_delivery_attempts', 'product_notification_subscriptions', 'wishlist_items', 'notification_preferences', 'notification_rate_buckets'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Customer engagement rollback requires empty MT-3.9 tables.');
            }
        }
        foreach (['notification_delivery_attempts', 'notification_rate_buckets', 'product_notification_subscriptions', 'wishlist_items', 'notification_preferences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
