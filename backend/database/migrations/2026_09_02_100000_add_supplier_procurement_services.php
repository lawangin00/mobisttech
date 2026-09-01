<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->string('supplier_code', 50)->collation('utf8mb4_bin');
            $table->string('name', 255);
            $table->string('tax_identifier', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('archived_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['outlet_id', 'supplier_code'], 'mt29_supplier_outlet_code');
            $table->unique(['id', 'outlet_id'], 'mt29_supplier_id_outlet');
            $table->index(['outlet_id', 'is_active', 'name'], 'mt29_supplier_outlet_status_name');
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete()->restrictOnUpdate();
            $table->string('name', 255);
            $table->string('job_title', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps(6);
            $table->index(['supplier_id', 'is_active', 'is_primary'], 'mt29_contact_supplier_status');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('supplier_id');
            $table->string('supplier_code_snapshot', 50)->collation('utf8mb4_bin');
            $table->string('supplier_name_snapshot', 255);
            $table->longText('supplier_contact_snapshot')->nullable();
            $table->string('order_number', 80)->collation('utf8mb4_bin')->unique();
            $table->enum('status', ['ordered', 'partially_received', 'received', 'cancelled'])->default('ordered');
            $table->dateTime('ordered_at', 6);
            $table->dateTime('expected_at', 6)->nullable();
            $table->dateTime('received_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('cancelled_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps(6);
            $table->foreign(['supplier_id', 'outlet_id'], 'mt29_fk_po_supplier_outlet')->references(['id', 'outlet_id'])->on('suppliers')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['id', 'outlet_id'], 'mt29_po_id_outlet');
            $table->index(['outlet_id', 'status', 'expected_at'], 'mt29_po_outlet_status_expected');
            $table->index(['supplier_id', 'ordered_at'], 'mt29_po_supplier_history');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('purchase_order_id');
            $table->foreignId('outlet_id');
            $table->foreignId('product_id');
            $table->unsignedInteger('ordered_quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->decimal('ordered_unit_cost', 19, 2);
            $table->decimal('planned_landed_unit_cost', 19, 2);
            $table->text('notes')->nullable();
            $table->timestamps(6);
            $table->foreign(['purchase_order_id', 'outlet_id'], 'mt29_fk_po_line_order_outlet')->references(['id', 'outlet_id'])->on('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['product_id', 'outlet_id'], 'mt29_fk_po_line_product_outlet')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['purchase_order_id', 'product_id'], 'mt29_po_line_product');
            $table->unique(['id', 'purchase_order_id'], 'mt29_po_line_id_order');
            $table->index(['product_id', 'purchase_order_id'], 'mt29_po_line_product_order');
        });
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT mt29_po_line_quantities CHECK (ordered_quantity > 0 AND received_quantity <= ordered_quantity)');
        DB::statement('ALTER TABLE purchase_order_lines ADD CONSTRAINT mt29_po_line_costs CHECK (ordered_unit_cost > 0 AND planned_landed_unit_cost > 0)');

        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('purchase_order_id');
            $table->foreignId('outlet_id');
            $table->string('receipt_number', 80)->collation('utf8mb4_bin')->unique();
            $table->dateTime('received_at', 6);
            $table->foreignId('received_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->text('notes')->nullable();
            $table->timestamps(6);
            $table->foreign(['purchase_order_id', 'outlet_id'], 'mt29_fk_receipt_order_outlet')->references(['id', 'outlet_id'])->on('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['id', 'purchase_order_id'], 'mt29_receipt_id_order');
            $table->index(['purchase_order_id', 'received_at'], 'mt29_receipt_order_history');
        });

        Schema::create('purchase_order_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('purchase_order_id');
            $table->foreignId('receipt_id');
            $table->foreignId('purchase_order_line_id');
            $table->foreignId('acquisition_id')->unique();
            $table->unsignedInteger('quantity');
            $table->decimal('received_unit_cost', 19, 2);
            $table->decimal('received_landed_unit_cost', 19, 2);
            $table->timestamps(6);
            $table->foreign(['receipt_id', 'purchase_order_id'], 'mt29_fk_receipt_line_receipt_order')->references(['id', 'purchase_order_id'])->on('purchase_order_receipts')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['purchase_order_line_id', 'purchase_order_id'], 'mt29_fk_receipt_line_po_line')->references(['id', 'purchase_order_id'])->on('purchase_order_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('acquisition_id', 'mt29_fk_receipt_line_acquisition')->references('id')->on('stock_acquisitions')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['receipt_id', 'purchase_order_line_id'], 'mt29_receipt_line_once');
            $table->index(['purchase_order_line_id', 'created_at'], 'mt29_receipt_line_history');
        });
        DB::statement('ALTER TABLE purchase_order_receipt_lines ADD CONSTRAINT mt29_receipt_line_values CHECK (quantity > 0 AND received_unit_cost > 0 AND received_landed_unit_cost > 0)');

        Schema::create('purchase_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('sequence');
            $table->string('event', 80)->collation('utf8mb4_bin');
            $table->foreignId('actor_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name_snapshot', 255);
            $table->text('actor_role_snapshot');
            $table->string('outlet_name_snapshot', 255);
            $table->longText('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('occurred_at', 6);
            $table->unique(['purchase_order_id', 'sequence'], 'mt29_po_event_sequence');
            $table->index(['purchase_order_id', 'occurred_at'], 'mt29_po_event_history');
        });

        Schema::create('reorder_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id');
            $table->foreignId('product_id');
            $table->unsignedInteger('reorder_threshold');
            $table->unsignedInteger('target_stock');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps(6);
            $table->foreign(['product_id', 'outlet_id'], 'mt29_fk_reorder_product_outlet')->references(['id', 'outlet_id'])->on('products')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['outlet_id', 'product_id'], 'mt29_reorder_outlet_product');
            $table->index(['outlet_id', 'is_active', 'reorder_threshold'], 'mt29_reorder_outlet_active');
        });
        DB::statement('ALTER TABLE reorder_policies ADD CONSTRAINT mt29_reorder_values CHECK (target_stock > reorder_threshold)');
    }

    public function down(): void
    {
        foreach (['purchase_order_receipt_lines', 'purchase_order_receipts', 'purchase_order_events', 'purchase_order_lines', 'purchase_orders', 'supplier_contacts', 'suppliers', 'reorder_policies'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Supplier/procurement rollback requires empty MT-2.9 business tables.');
            }
        }
        Schema::dropIfExists('reorder_policies');
        Schema::dropIfExists('purchase_order_receipt_lines');
        Schema::dropIfExists('purchase_order_receipts');
        Schema::dropIfExists('purchase_order_events');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
