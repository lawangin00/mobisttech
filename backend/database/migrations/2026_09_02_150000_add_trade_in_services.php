<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_ins', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_id');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('acquisition_id')->nullable()->unique()->constrained('stock_acquisitions')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('monetary_adjustment_id')->nullable()->collation('utf8mb4_bin')->unique();
            $table->foreign('monetary_adjustment_id')->references('id')->on('monetary_adjustments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('received_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->string('seller_name', 255);
            $table->string('seller_cnic', 15);
            $table->string('seller_phone', 20);
            $table->text('seller_address');
            $table->string('device_serial', 100)->nullable();
            $table->string('device_condition', 150);
            $table->json('diagnostics');
            $table->decimal('valuation_amount', 19, 2);
            $table->enum('settlement_mode', ['purchase', 'sale_credit']);
            $table->enum('status', ['pending', 'approved', 'received', 'cancelled'])->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->text('cancel_reason')->nullable();
            $table->json('approval_snapshot')->nullable();
            $table->char('approval_sha256', 64)->collation('utf8mb4_bin')->nullable();
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('received_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign(['product_id', 'outlet_id'], 'mt213_fk_trade_product')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['outlet_id', 'status', 'created_at'], 'mt213_trade_status');
        });
        DB::statement("ALTER TABLE trade_ins ADD CONSTRAINT mt213_trade_value CHECK (valuation_amount > 0), ADD CONSTRAINT mt213_trade_settlement CHECK ((settlement_mode = 'purchase' AND invoice_id IS NULL) OR (settlement_mode = 'sale_credit' AND invoice_id IS NOT NULL)), ADD CONSTRAINT mt213_trade_state CHECK ((status = 'pending' AND approved_at IS NULL AND received_at IS NULL AND cancelled_at IS NULL AND acquisition_id IS NULL AND monetary_adjustment_id IS NULL) OR (status = 'approved' AND approved_at IS NOT NULL AND received_at IS NULL AND cancelled_at IS NULL AND acquisition_id IS NULL AND monetary_adjustment_id IS NULL) OR (status = 'received' AND approved_at IS NOT NULL AND received_at IS NOT NULL AND cancelled_at IS NULL AND acquisition_id IS NOT NULL AND ((settlement_mode = 'purchase' AND monetary_adjustment_id IS NULL) OR (settlement_mode = 'sale_credit' AND monetary_adjustment_id IS NOT NULL))) OR (status = 'cancelled' AND received_at IS NULL AND cancelled_at IS NOT NULL AND acquisition_id IS NULL AND monetary_adjustment_id IS NULL))");

        Schema::create('trade_in_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_in_id')->constrained('trade_ins')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('slot_no');
            $table->string('identifier', 50)->collation('utf8mb4_bin');
            $table->dateTime('released_at', 6)->nullable();
            $table->string('active_identifier', 50)->collation('utf8mb4_bin')->nullable()->storedAs('CASE WHEN released_at IS NULL THEN identifier ELSE NULL END')->unique();
            $table->dateTime('created_at', 6);
            $table->unique(['trade_in_id', 'slot_no'], 'mt213_trade_identifier_slot');
            $table->index(['trade_in_id', 'released_at'], 'mt213_trade_identifier_state');
        });
        DB::statement('ALTER TABLE trade_in_identifiers ADD CONSTRAINT mt213_trade_identifier_slot_check CHECK (slot_no > 0)');

        Schema::create('trade_in_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_in_id')->constrained('trade_ins')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('sequence');
            $table->enum('event_type', ['created', 'approved', 'received', 'cancelled']);
            $table->foreignId('actor_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('created_at', 6);
            $table->unique(['trade_in_id', 'sequence'], 'mt213_trade_event_sequence');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('trade_ins') && DB::table('trade_ins')->exists()) {
            throw new RuntimeException('Trade-in rollback requires empty trade-in history.');
        }
        Schema::dropIfExists('trade_in_events');
        Schema::dropIfExists('trade_in_identifiers');
        Schema::dropIfExists('trade_ins');
    }
};
