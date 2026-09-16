<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->string('transfer_number', 80)->collation('utf8mb4_bin')->unique();
            $table->foreignId('source_outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('destination_outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('status', ['draft', 'in_transit', 'partially_received', 'received', 'rejected'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('dispatched_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('dispatched_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['id', 'source_outlet_id', 'destination_outlet_id'], 'mt211_transfer_id_outlets');
            $table->index(['source_outlet_id', 'status', 'created_at'], 'mt211_transfer_source_status');
            $table->index(['destination_outlet_id', 'status', 'created_at'], 'mt211_transfer_destination_status');
        });
        DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT mt211_transfer_outlets CHECK (source_outlet_id <> destination_outlet_id)');

        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('stock_transfer_id');
            $table->foreignId('source_outlet_id');
            $table->foreignId('destination_outlet_id');
            $table->foreignId('source_product_id');
            $table->foreignId('destination_product_id');
            $table->boolean('tracked_serialized');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('rejected_quantity')->default(0);
            $table->longText('product_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->foreign(['stock_transfer_id', 'source_outlet_id', 'destination_outlet_id'], 'mt211_fk_line_transfer')
                ->references(['id', 'source_outlet_id', 'destination_outlet_id'])->on('stock_transfers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['source_product_id', 'source_outlet_id'], 'mt211_fk_line_source_product')
                ->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['destination_product_id', 'destination_outlet_id'], 'mt211_fk_line_destination_product')
                ->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['stock_transfer_id', 'source_product_id', 'destination_product_id'], 'mt211_transfer_product_pair');
            $table->unique(['id', 'stock_transfer_id'], 'mt211_line_id_transfer');
            $table->index(['source_product_id', 'stock_transfer_id'], 'mt211_line_source_product');
            $table->index(['destination_product_id', 'stock_transfer_id'], 'mt211_line_destination_product');
        });
        DB::statement('ALTER TABLE stock_transfer_lines ADD CONSTRAINT mt211_line_quantities CHECK (quantity > 0 AND received_quantity + rejected_quantity <= quantity)');

        Schema::create('stock_transfer_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_line_id')->constrained('stock_transfer_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('source_stock_unit_id')->constrained('stock_units')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('source_stock_unit_public_id_snapshot')->collation('utf8mb4_bin');
            $table->foreignId('successor_stock_unit_id')->nullable()->unique()->constrained('stock_units')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('status', ['planned', 'in_transit', 'received', 'rejected'])->default('planned');
            $table->longText('source_unit_snapshot')->nullable();
            $table->char('snapshot_sha256', 64)->nullable()->collation('utf8mb4_bin');
            $table->dateTime('resolved_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['stock_transfer_line_id', 'source_stock_unit_id'], 'mt211_line_source_unit');
            $table->index(['source_stock_unit_id', 'status'], 'mt211_source_unit_status');
        });

        Schema::create('stock_transfer_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('destination_outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('processed_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('transfer_version_before');
            $table->unsignedInteger('transfer_version_after');
            $table->text('notes')->nullable();
            $table->longText('receipt_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('processed_at', 6);
            $table->timestamps(6);
            $table->unique(['id', 'stock_transfer_id'], 'mt211_receipt_id_transfer');
            $table->index(['stock_transfer_id', 'processed_at'], 'mt211_receipt_transfer_time');
        });

        Schema::create('stock_transfer_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_receipt_id');
            $table->foreignId('stock_transfer_id');
            $table->foreignId('stock_transfer_line_id');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->unsignedInteger('rejected_quantity')->default(0);
            $table->longText('line_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->foreign(['stock_transfer_receipt_id', 'stock_transfer_id'], 'mt211_fk_receipt_line_receipt')
                ->references(['id', 'stock_transfer_id'])->on('stock_transfer_receipts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['stock_transfer_line_id', 'stock_transfer_id'], 'mt211_fk_receipt_line_transfer_line')
                ->references(['id', 'stock_transfer_id'])->on('stock_transfer_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['stock_transfer_receipt_id', 'stock_transfer_line_id'], 'mt211_receipt_line_once');
        });
        DB::statement('ALTER TABLE stock_transfer_receipt_lines ADD CONSTRAINT mt211_receipt_line_action CHECK (received_quantity + rejected_quantity > 0)');

        DB::statement("ALTER TABLE product_imeis MODIFY status ENUM('in_stock','sold','adjusted_out','transferred_out') NOT NULL DEFAULT 'in_stock'");
    }

    public function down(): void
    {
        foreach (['stock_transfer_receipt_lines', 'stock_transfer_receipts', 'stock_transfer_units', 'stock_transfer_lines', 'stock_transfers'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Stock-transfer rollback requires empty MT-2.11 business tables.');
            }
        }
        if (DB::table('product_imeis')->where('status', 'transferred_out')->exists()
            || DB::table('stock_units')->where('status', 'transferred_out')->exists()) {
            throw new RuntimeException('Cannot remove transfer history while transferred stock occurrences exist.');
        }
        Schema::dropIfExists('stock_transfer_receipt_lines');
        Schema::dropIfExists('stock_transfer_receipts');
        Schema::dropIfExists('stock_transfer_units');
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
        DB::statement("ALTER TABLE product_imeis MODIFY status ENUM('in_stock','sold','adjusted_out') NOT NULL DEFAULT 'in_stock'");
    }
};
