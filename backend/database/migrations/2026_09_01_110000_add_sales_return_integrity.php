<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->uuid('public_id')->collation('utf8mb4_bin')->after('id');
            $table->unsignedBigInteger('version')->default(1)->after('public_id');
            $table->unsignedInteger('returned_quantity')->default(0)->after('quantity');
            $table->unique('public_id', 'mt25_sales_public');
            $table->unique(['id', 'invoice_id'], 'mt25_sales_invoice');
        });
        DB::statement('ALTER TABLE sales ADD CONSTRAINT mt25_sales_money CHECK (sale_price >= 0 AND total_price >= 0 AND purchase_price >= 0 AND discount_allocated >= 0 AND net_total_price >= 0 AND discount_allocated <= total_price AND net_total_price = total_price - discount_allocated AND returned_quantity <= quantity)');

        Schema::table('returns', function (Blueprint $table) {
            $table->unique(['id', 'invoice_id'], 'mt25_returns_invoice');
        });
        DB::statement("ALTER TABLE returns ADD CONSTRAINT mt25_returns_status CHECK (status IN ('accepted'))");

        Schema::table('return_lines', function (Blueprint $table) {
            $table->uuid('public_id')->collation('utf8mb4_bin')->after('id');
            $table->unsignedBigInteger('invoice_id')->after('return_id');
            $table->unsignedBigInteger('successor_stock_unit_id')->nullable()->after('stock_unit_id');
            $table->decimal('unit_price', 19, 2)->after('quantity');
            $table->decimal('discount_amount', 19, 2)->after('unit_price');
            $table->decimal('net_amount', 19, 2)->after('discount_amount');
            $table->decimal('purchase_amount', 19, 2)->after('net_amount');
            $table->char('currency', 3)->collation('utf8mb4_bin')->default('PKR')->after('purchase_amount');
            $table->json('sale_snapshot')->after('currency');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin')->after('sale_snapshot');
            $table->unique('public_id', 'mt25_return_lines_public');
            $table->unique('successor_stock_unit_id', 'mt25_return_lines_successor');
            $table->foreign(['return_id', 'invoice_id'], 'mt25_fk_return_invoice')->references(['id', 'invoice_id'])->on('returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['sale_id', 'invoice_id'], 'mt25_fk_return_sale')->references(['id', 'invoice_id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('successor_stock_unit_id', 'mt25_fk_return_successor')->references('id')->on('stock_units')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement("ALTER TABLE return_lines ADD CONSTRAINT mt25_return_money CHECK (unit_price >= 0 AND discount_amount >= 0 AND net_amount >= 0 AND purchase_amount >= 0 AND discount_amount <= unit_price * quantity AND net_amount = unit_price * quantity - discount_amount AND currency = 'PKR'), ADD CONSTRAINT mt25_return_disposition CHECK (disposition IN ('sellable','damaged','quarantined'))");
    }

    public function down(): void
    {
        if (DB::table('returns')->exists() || DB::table('sales')->exists()) {
            throw new RuntimeException('Sales integrity rollback requires empty sale and return history.');
        }
        DB::statement('ALTER TABLE return_lines DROP CHECK mt25_return_money, DROP CHECK mt25_return_disposition');
        DB::statement('ALTER TABLE returns DROP CHECK mt25_returns_status');
        DB::statement('ALTER TABLE sales DROP CHECK mt25_sales_money');
        Schema::table('return_lines', function (Blueprint $table) {
            $table->dropForeign('mt25_fk_return_successor');
            $table->dropForeign('mt25_fk_return_sale');
            $table->dropForeign('mt25_fk_return_invoice');
            $table->dropUnique('mt25_return_lines_successor');
            $table->dropUnique('mt25_return_lines_public');
            $table->dropColumn(['public_id', 'invoice_id', 'successor_stock_unit_id', 'unit_price', 'discount_amount', 'net_amount', 'purchase_amount', 'currency', 'sale_snapshot', 'snapshot_sha256']);
        });
        Schema::table('returns', fn (Blueprint $table) => $table->dropUnique('mt25_returns_invoice'));
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique('mt25_sales_invoice');
            $table->dropUnique('mt25_sales_public');
            $table->dropColumn(['public_id', 'version', 'returned_quantity']);
        });
    }
};
