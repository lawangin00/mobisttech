<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No publication or feature is enabled by this additive migration.
        Schema::create('website_operating_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->enum('mode', ['digital_only', 'hybrid', 'commerce_only']);
            $table->unsignedBigInteger('version');
            $table->foreignId('revision_id')->constrained('site_configuration_revisions')->restrictOnDelete();
            $table->dateTime('published_at', 6);
        });
        DB::statement('ALTER TABLE website_operating_profiles ADD CONSTRAINT profile_singleton CHECK (id = 1 AND version > 0)');

        Schema::create('stock_unit_lineage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_unit_id')->unique()->constrained('stock_units')->restrictOnDelete();
            $table->foreignId('successor_unit_id')->unique()->constrained('stock_units')->restrictOnDelete();
            $table->enum('reason', ['transfer', 'customer_return']);
            $table->uuid('operation_id')->collation('utf8mb4_bin')->index();
            $table->dateTime('created_at', 6);
        });
        // Successors are newly allocated occurrences, never a reparented historic row.
        DB::statement('ALTER TABLE stock_unit_lineage ADD CONSTRAINT lineage_forward_only CHECK (successor_unit_id > source_unit_id)');

        Schema::create('inventory_custody_holds', function (Blueprint $table) {
            $table->id();
            $table->uuid('operation_id')->collation('utf8mb4_bin')->index();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('destination_product_id')->constrained('products')->restrictOnDelete();
            $table->unsignedBigInteger('stock_unit_id')->nullable();
            $table->foreign(['stock_unit_id', 'product_id'])->references(['id', 'product_id'])->on('stock_units')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->dateTime('released_at', 6)->nullable();
            $table->unsignedBigInteger('active_unit_id')->nullable()->storedAs('CASE WHEN released_at IS NULL THEN stock_unit_id ELSE NULL END')->unique();
            $table->dateTime('created_at', 6);
            $table->index(['product_id', 'released_at']);
        });
        DB::statement('ALTER TABLE inventory_custody_holds ADD CONSTRAINT custody_shape CHECK (quantity > 0 AND (stock_unit_id IS NULL OR quantity = 1) AND product_id <> destination_product_id)');

        Schema::create('acquisition_source_references', function (Blueprint $table) {
            $table->uuid('id')->collation('utf8mb4_bin')->primary();
            $table->foreignId('acquisition_id')->unique()->constrained('stock_acquisitions')->restrictOnDelete();
            $table->enum('kind', ['purchase_order_receipt', 'trade_in']);
            $table->uuid('source_line_id')->collation('utf8mb4_bin')->index();
            $table->dateTime('created_at', 6);
        });

        Schema::create('monetary_adjustments', function (Blueprint $table) {
            $table->uuid('id')->collation('utf8mb4_bin')->primary();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();
            $table->enum('kind', ['manual_discount', 'promotion', 'loyalty_redemption', 'trade_in_credit']);
            $table->enum('treatment', ['discount', 'tender']);
            // Non-manual sources are one effect identity, not reusable feature configuration IDs.
            $table->uuid('source_reference')->nullable()->collation('utf8mb4_bin')->unique();
            $table->decimal('amount', 19, 2);
            $table->char('currency', 3)->collation('utf8mb4_bin');
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('created_at', 6);
        });
        DB::statement("ALTER TABLE monetary_adjustments ADD CONSTRAINT adjustment_owner CHECK ((invoice_id IS NOT NULL) + (order_id IS NOT NULL) = 1), ADD CONSTRAINT adjustment_value CHECK (amount > 0 AND currency = 'PKR'), ADD CONSTRAINT adjustment_treatment CHECK ((kind = 'trade_in_credit' AND treatment = 'tender') OR (kind <> 'trade_in_credit' AND treatment = 'discount')), ADD CONSTRAINT adjustment_source CHECK (kind = 'manual_discount' OR source_reference IS NOT NULL)");

        Schema::create('project_milestone_identities', function (Blueprint $table) {
            $table->uuid('id')->collation('utf8mb4_bin')->primary();
            $table->foreignId('quote_id')->constrained('project_quotes')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->decimal('approved_amount', 19, 2);
            $table->char('currency', 3)->collation('utf8mb4_bin');
            $table->json('approved_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('created_at', 6);
            $table->unique(['quote_id', 'sequence']);
        });
        DB::statement("ALTER TABLE project_milestone_identities ADD CONSTRAINT milestone_value CHECK (sequence > 0 AND approved_amount > 0 AND currency = 'PKR')");
        Schema::create('order_item_milestones', function (Blueprint $table) {
            $table->foreignId('order_item_id')->primary()->constrained('order_items')->restrictOnDelete();
            $table->uuid('milestone_id')->collation('utf8mb4_bin');
            $table->foreign('milestone_id')->references('id')->on('project_milestone_identities')->restrictOnDelete();
            $table->unique('milestone_id');
        });
    }

    public function down(): void
    {
        // Deployment rollback must not discard business records created by later consumers.
        foreach (['order_item_milestones', 'project_milestone_identities', 'monetary_adjustments', 'acquisition_source_references', 'inventory_custody_holds', 'stock_unit_lineage', 'website_operating_profiles'] as $name) {
            if (Schema::hasTable($name) && DB::table($name)->exists()) {
                throw new RuntimeException('Addendum rollback requires an empty extension schema; restore/reconcile explicitly.');
            }
        }
        foreach (['order_item_milestones', 'project_milestone_identities', 'monetary_adjustments', 'acquisition_source_references', 'inventory_custody_holds', 'stock_unit_lineage', 'website_operating_profiles'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
