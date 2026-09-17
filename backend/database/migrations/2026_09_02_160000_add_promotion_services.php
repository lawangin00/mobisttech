<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->restrictOnDelete();
            $table->string('name', 255);
            $table->enum('mode', ['automatic', 'coupon']);
            $table->string('code', 80)->nullable()->collation('utf8mb4_bin')->unique();
            $table->enum('discount_type', ['fixed', 'percentage']);
            $table->decimal('discount_value', 19, 2);
            $table->decimal('max_discount', 19, 2)->nullable();
            $table->decimal('min_subtotal', 19, 2)->default('0');
            $table->dateTime('starts_at', 6)->nullable();
            $table->dateTime('ends_at', 6)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->boolean('customer_required')->default(false);
            $table->boolean('stackable')->default(false);
            $table->integer('priority')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamps(6);
            $table->index(['outlet_id', 'status', 'mode', 'priority']);
        });
        DB::statement("ALTER TABLE promotions ADD CONSTRAINT mt214_promotion_value CHECK (discount_value > 0 AND ((discount_type = 'percentage' AND discount_value <= 100) OR discount_type = 'fixed')), ADD CONSTRAINT mt214_promotion_caps CHECK (min_subtotal >= 0 AND (max_discount IS NULL OR max_discount > 0)), ADD CONSTRAINT mt214_promotion_window CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at > starts_at), ADD CONSTRAINT mt214_promotion_limits CHECK ((usage_limit IS NULL OR usage_limit > 0) AND (per_customer_limit IS NULL OR per_customer_limit > 0)), ADD CONSTRAINT mt214_coupon_shape CHECK ((mode = 'coupon' AND code IS NOT NULL) OR (mode = 'automatic' AND code IS NULL))");

        Schema::create('promotion_products', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->primary(['promotion_id', 'product_id']);
        });
        Schema::create('promotion_categories', function (Blueprint $table) {
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('category', 40)->collation('utf8mb4_bin');
            $table->primary(['promotion_id', 'category']);
        });

        Schema::create('promotion_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->enum('channel', ['pos', 'website']);
            $table->char('owner_key', 64)->collation('utf8mb4_bin');
            $table->char('customer_key', 64)->nullable()->collation('utf8mb4_bin');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->decimal('discount_amount', 19, 2);
            $table->enum('status', ['active', 'released'])->default('active');
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('released_at', 6)->nullable();
            $table->string('release_reason', 255)->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['promotion_id', 'channel', 'owner_key'], 'mt214_claim_owner');
            $table->index(['promotion_id', 'status']);
            $table->index(['promotion_id', 'customer_key', 'status'], 'mt214_claim_customer');
            $table->index(['order_id', 'status']);
        });
        DB::statement("ALTER TABLE promotion_claims ADD CONSTRAINT mt214_claim_money CHECK (discount_amount > 0), ADD CONSTRAINT mt214_claim_release CHECK ((status = 'active' AND released_at IS NULL AND release_reason IS NULL) OR (status = 'released' AND released_at IS NOT NULL AND release_reason IS NOT NULL)), ADD CONSTRAINT mt214_claim_owner CHECK ((invoice_id IS NULL) + (order_id IS NULL) >= 1)");

        Schema::create('promotion_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->restrictOnDelete();
            $table->foreignId('promotion_claim_id')->nullable()->constrained('promotion_claims')->restrictOnDelete();
            $table->enum('event_type', ['configured', 'updated', 'claimed', 'released']);
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('created_at', 6);
            $table->index(['promotion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['promotion_events', 'promotion_claims', 'promotion_products', 'promotion_categories', 'promotions'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Promotion rollback requires empty promotion configuration and history.');
            }
        }
        Schema::dropIfExists('promotion_events');
        Schema::dropIfExists('promotion_claims');
        Schema::dropIfExists('promotion_products');
        Schema::dropIfExists('promotion_categories');
        Schema::dropIfExists('promotions');
    }
};
