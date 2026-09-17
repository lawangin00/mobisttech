<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->unsignedBigInteger('version')->unique();
            $table->boolean('enabled')->default(false);
            $table->decimal('earn_basis_amount', 19, 2);
            $table->unsignedInteger('earn_points');
            $table->decimal('redemption_value', 19, 2);
            $table->unsignedInteger('min_redeem_points')->default(1);
            $table->unsignedInteger('max_redeem_points')->nullable();
            $table->unsignedInteger('daily_redeem_points')->nullable();
            $table->unsignedInteger('expiry_days')->nullable();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->dateTime('created_at', 6);
        });
        DB::statement('ALTER TABLE loyalty_configurations ADD CONSTRAINT mt215_config_values CHECK (earn_basis_amount > 0 AND earn_points > 0 AND redemption_value > 0), ADD CONSTRAINT mt215_config_limits CHECK (min_redeem_points > 0 AND (max_redeem_points IS NULL OR max_redeem_points >= min_redeem_points) AND (daily_redeem_points IS NULL OR daily_redeem_points > 0) AND (expiry_days IS NULL OR (expiry_days >= 1 AND expiry_days <= 3650)))');

        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('customers')->restrictOnDelete();
            $table->bigInteger('balance_points')->default(0);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps(6);
        });

        Schema::create('loyalty_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->uuid('monetary_adjustment_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('loyalty_accounts')->restrictOnDelete();
            $table->enum('channel', ['pos', 'website']);
            $table->char('owner_key', 64)->collation('utf8mb4_bin');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->unsignedBigInteger('points');
            $table->decimal('discount_amount', 19, 2);
            $table->enum('status', ['active', 'released'])->default('active');
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('released_at', 6)->nullable();
            $table->string('release_reason', 255)->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['channel', 'owner_key'], 'mt215_claim_owner');
            $table->index(['customer_id', 'created_at'], 'mt215_claim_customer_day');
            $table->index(['order_id', 'status'], 'mt215_claim_order');
        });
        DB::statement("ALTER TABLE loyalty_claims ADD CONSTRAINT mt215_claim_values CHECK (points > 0 AND discount_amount > 0), ADD CONSTRAINT mt215_claim_release CHECK ((status = 'active' AND released_at IS NULL AND release_reason IS NULL) OR (status = 'released' AND released_at IS NOT NULL AND release_reason IS NOT NULL)), ADD CONSTRAINT mt215_claim_owner_shape CHECK ((invoice_id IS NULL) + (order_id IS NULL) >= 1)");

        Schema::create('loyalty_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('loyalty_accounts')->restrictOnDelete();
            $table->foreignId('loyalty_claim_id')->nullable()->constrained('loyalty_claims')->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->foreignId('return_id')->nullable()->constrained('returns')->restrictOnDelete();
            $table->enum('entry_type', ['earn', 'redeem', 'redemption_release', 'earn_return_reversal', 'redeem_return_restore', 'expire']);
            $table->enum('direction', ['credit', 'debit']);
            $table->unsignedBigInteger('points');
            $table->string('event_key', 191)->collation('utf8mb4_bin')->unique();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('created_at', 6);
            $table->index(['customer_id', 'created_at'], 'mt215_entry_customer');
        });
        DB::statement("ALTER TABLE loyalty_entries ADD CONSTRAINT mt215_entry_points CHECK (points > 0), ADD CONSTRAINT mt215_entry_direction CHECK ((entry_type IN ('earn','redemption_release','redeem_return_restore') AND direction = 'credit') OR (entry_type IN ('redeem','earn_return_reversal','expire') AND direction = 'debit'))");

        Schema::create('loyalty_earn_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('loyalty_accounts')->restrictOnDelete();
            $table->foreignId('source_entry_id')->unique()->constrained('loyalty_entries')->restrictOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained('invoices')->restrictOnDelete();
            $table->unsignedBigInteger('points_earned');
            $table->unsignedBigInteger('points_remaining');
            $table->dateTime('expires_at', 6)->nullable();
            $table->timestamps(6);
            $table->index(['account_id', 'expires_at'], 'mt215_lot_expiry');
        });
        DB::statement('ALTER TABLE loyalty_earn_lots ADD CONSTRAINT mt215_lot_points CHECK (points_earned > 0 AND points_remaining <= points_earned)');

        Schema::create('loyalty_claim_lots', function (Blueprint $table) {
            $table->foreignId('loyalty_claim_id')->constrained('loyalty_claims')->restrictOnDelete();
            $table->foreignId('loyalty_earn_lot_id')->constrained('loyalty_earn_lots')->restrictOnDelete();
            $table->unsignedBigInteger('points');
            $table->unsignedBigInteger('restored_points')->default(0);
            $table->dateTime('created_at', 6);
            $table->primary(['loyalty_claim_id', 'loyalty_earn_lot_id'], 'mt215_claim_lot_pk');
        });
        DB::statement('ALTER TABLE loyalty_claim_lots ADD CONSTRAINT mt215_claim_lot_points CHECK (points > 0 AND restored_points <= points)');
    }

    public function down(): void
    {
        foreach (['loyalty_claim_lots', 'loyalty_earn_lots', 'loyalty_entries', 'loyalty_claims', 'loyalty_accounts', 'loyalty_configurations'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Loyalty rollback requires empty loyalty configuration and history.');
            }
        }        Schema::dropIfExists('loyalty_claim_lots');
        Schema::dropIfExists('loyalty_earn_lots');
        Schema::dropIfExists('loyalty_entries');
        Schema::dropIfExists('loyalty_claims');
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_configurations');
    }
};
