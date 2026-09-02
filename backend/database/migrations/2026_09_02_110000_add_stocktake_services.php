<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktake_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->string('session_number', 80)->collation('utf8mb4_bin')->unique();
            $table->enum('kind', ['full', 'cycle']);
            $table->enum('status', ['counting', 'submitted', 'approved'])->default('counting');
            $table->text('notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('started_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('started_at', 6);
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('approved_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['id', 'outlet_id'], 'mt210_stocktake_id_outlet');
            $table->index(['outlet_id', 'status', 'started_at'], 'mt210_stocktake_outlet_status');
        });

        Schema::create('stocktake_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('stocktake_session_id');
            $table->foreignId('outlet_id');
            $table->foreignId('product_id');
            $table->uuid('product_public_id_snapshot')->collation('utf8mb4_bin');
            $table->string('product_name_snapshot', 255);
            $table->boolean('tracked_serialized');
            $table->integer('baseline_quantity')->unsigned();
            $table->integer('baseline_held_quantity')->unsigned();
            $table->integer('baseline_available_quantity')->unsigned();
            $table->unsignedBigInteger('baseline_product_version');
            $table->unsignedBigInteger('baseline_movement_id')->default(0);
            $table->dateTime('baseline_at', 6);
            $table->unsignedSmallInteger('current_iteration')->default(1);
            $table->integer('counted_quantity')->unsigned()->nullable();
            $table->integer('expected_quantity_at_count')->unsigned()->nullable();
            $table->integer('variance')->nullable();
            $table->string('reason_code', 40)->collation('utf8mb4_bin')->nullable();
            $table->text('reason_notes')->nullable();
            $table->foreignId('counted_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('counted_at', 6)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps(6);
            $table->foreign(['stocktake_session_id', 'outlet_id'], 'mt210_fk_line_session_outlet')->references(['id', 'outlet_id'])->on('stocktake_sessions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['product_id', 'outlet_id'], 'mt210_fk_line_product_outlet')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['stocktake_session_id', 'product_id'], 'mt210_stocktake_product_once');
            $table->unique(['id', 'stocktake_session_id'], 'mt210_line_id_session');
            $table->index(['outlet_id', 'product_id', 'counted_at'], 'mt210_line_outlet_product');
        });
        DB::statement('ALTER TABLE stocktake_lines ADD CONSTRAINT mt210_line_baseline CHECK (baseline_held_quantity <= baseline_quantity AND baseline_available_quantity <= baseline_quantity)');
        DB::statement('ALTER TABLE stocktake_lines ADD CONSTRAINT mt210_line_variance CHECK (variance IS NULL OR (counted_quantity IS NOT NULL AND expected_quantity_at_count IS NOT NULL AND variance = CAST(counted_quantity AS SIGNED) - CAST(expected_quantity_at_count AS SIGNED)))');

        Schema::create('stocktake_unit_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stocktake_line_id')->constrained('stocktake_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('stock_unit_id')->constrained('stock_units')->restrictOnDelete()->restrictOnUpdate();
            $table->uuid('stock_unit_public_id_snapshot')->collation('utf8mb4_bin');
            $table->unsignedBigInteger('stock_unit_version');
            $table->integer('unit_no')->unsigned();
            $table->longText('identifier_snapshot')->nullable();
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('captured_at', 6);
            $table->unique(['stocktake_line_id', 'stock_unit_id'], 'mt210_baseline_line_unit');
            $table->index(['stock_unit_id', 'stocktake_line_id'], 'mt210_baseline_unit_line');
        });

        Schema::create('stocktake_counts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('stocktake_session_id');
            $table->foreignId('stocktake_line_id');
            $table->unsignedSmallInteger('iteration');
            $table->integer('expected_quantity')->unsigned();
            $table->integer('counted_quantity')->unsigned();
            $table->integer('variance');
            $table->string('reason_code', 40)->collation('utf8mb4_bin')->nullable();
            $table->text('reason_notes')->nullable();
            $table->foreignId('counted_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->longText('count_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('counted_at', 6);
            $table->timestamps(6);
            $table->foreign(['stocktake_line_id', 'stocktake_session_id'], 'mt210_fk_count_line_session')->references(['id', 'stocktake_session_id'])->on('stocktake_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['stocktake_line_id', 'iteration'], 'mt210_count_line_iteration');
            $table->index(['stocktake_session_id', 'counted_at'], 'mt210_count_session_time');
        });
        DB::statement('ALTER TABLE stocktake_counts ADD CONSTRAINT mt210_count_variance CHECK (variance = CAST(counted_quantity AS SIGNED) - CAST(expected_quantity AS SIGNED))');
        DB::statement('ALTER TABLE stocktake_counts ADD CONSTRAINT mt210_count_reason CHECK (variance = 0 OR reason_code IS NOT NULL)');

        Schema::create('stocktake_recounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('stocktake_session_id');
            $table->foreignId('stocktake_line_id');
            $table->unsignedSmallInteger('from_iteration');
            $table->unsignedSmallInteger('to_iteration');
            $table->text('reason');
            $table->foreignId('requested_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->longText('prior_count_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('requested_at', 6);
            $table->foreign(['stocktake_line_id', 'stocktake_session_id'], 'mt210_fk_recount_line_session')->references(['id', 'stocktake_session_id'])->on('stocktake_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['stocktake_line_id', 'to_iteration'], 'mt210_recount_line_iteration');
            $table->index(['stocktake_session_id', 'requested_at'], 'mt210_recount_session_time');
        });
        DB::statement('ALTER TABLE stocktake_recounts ADD CONSTRAINT mt210_recount_iteration CHECK (to_iteration = from_iteration + 1)');

        DB::statement("ALTER TABLE product_imeis MODIFY status ENUM('in_stock','sold','adjusted_out') NOT NULL DEFAULT 'in_stock'");

        Schema::create('stocktake_approvals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('stocktake_session_id')->unique()->constrained('stocktake_sessions')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('approved_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name_snapshot', 255);
            $table->text('actor_role_snapshot');
            $table->string('outlet_name_snapshot', 255);
            $table->text('notes')->nullable();
            $table->longText('approval_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('approved_at', 6);
            $table->timestamps(6);
            $table->index(['approved_by_admin_id', 'approved_at'], 'mt210_approval_actor_time');
        });
    }

    public function down(): void
    {
        foreach (['stocktake_approvals', 'stocktake_recounts', 'stocktake_counts', 'stocktake_unit_baselines', 'stocktake_lines', 'stocktake_sessions'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Stocktake rollback requires empty MT-2.10 business tables.');
            }
        }
        Schema::dropIfExists('stocktake_approvals');
        Schema::dropIfExists('stocktake_recounts');
        Schema::dropIfExists('stocktake_counts');
        Schema::dropIfExists('stocktake_unit_baselines');
        Schema::dropIfExists('stocktake_lines');
        Schema::dropIfExists('stocktake_sessions');
        if (DB::table('product_imeis')->where('status', 'adjusted_out')->exists()) {
            throw new RuntimeException('Cannot remove adjusted_out IMEI history while stocktake adjustments exist.');
        }
        DB::statement("ALTER TABLE product_imeis MODIFY status ENUM('in_stock','sold') NOT NULL DEFAULT 'in_stock'");
    }
};
