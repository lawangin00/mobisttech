<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->unique()->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps(6);
        });

        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->string('repair_number', 64)->collation('utf8mb4_bin')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete()->restrictOnUpdate();
            $table->string('customer_name', 255);
            $table->string('customer_phone', 40)->nullable();
            $table->string('device_label', 255);
            $table->enum('identifier_type', ['imei', 'serial', 'other']);
            $table->string('identifier_value', 191)->collation('utf8mb4_bin');
            $table->string('active_identifier', 191)->nullable()->collation('utf8mb4_bin');
            $table->text('issue_description');
            $table->string('received_condition', 150)->nullable();
            $table->string('accessories_received', 500)->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('internal_notes')->nullable();
            $table->enum('status', ['received', 'diagnosing', 'awaiting_approval', 'approved', 'repairing', 'ready_for_collection', 'delivered', 'closed', 'cancelled'])->default('received');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('handled_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->string('handled_by_name', 255);
            $table->unsignedBigInteger('version')->default(1);
            $table->dateTime('received_at', 6);
            $table->dateTime('diagnosed_at', 6)->nullable();
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('ready_at', 6)->nullable();
            $table->dateTime('delivered_at', 6)->nullable();
            $table->dateTime('closed_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['outlet_id', 'active_identifier'], 'mt216_repair_active_identifier');
            $table->index(['outlet_id', 'status', 'received_at'], 'mt216_repair_outlet_status');
        });

        Schema::create('repair_estimates', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('repair_job_id')->constrained('repair_jobs')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('version');
            $table->enum('status', ['proposed', 'approved', 'rejected'])->default('proposed');
            $table->decimal('parts_total', 19, 2)->default('0.00');
            $table->decimal('labor_total', 19, 2)->default('0.00');
            $table->decimal('grand_total', 19, 2);
            $table->text('notes')->nullable();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at', 6);
            $table->dateTime('decided_at', 6)->nullable();
            $table->unique(['repair_job_id', 'version'], 'mt216_repair_estimate_version');
            $table->index(['repair_job_id', 'status'], 'mt216_repair_estimate_status');
        });
        DB::statement('ALTER TABLE repair_estimates ADD CONSTRAINT mt216_estimate_money CHECK (parts_total >= 0 AND labor_total >= 0 AND grand_total = parts_total + labor_total AND grand_total >= 0)');

        Schema::create('repair_estimate_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_estimate_id')->constrained('repair_estimates')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('line_no');
            $table->enum('line_type', ['part', 'labor']);
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $table->string('description', 500);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 19, 2);
            $table->decimal('line_total', 19, 2);
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->unique(['repair_estimate_id', 'line_no'], 'mt216_repair_estimate_line');
            $table->index(['product_id', 'line_type'], 'mt216_repair_line_product');
        });
        DB::statement("ALTER TABLE repair_estimate_lines ADD CONSTRAINT mt216_estimate_line_shape CHECK (((line_type = 'part') AND product_id IS NOT NULL) OR ((line_type = 'labor') AND product_id IS NULL)), ADD CONSTRAINT mt216_estimate_line_money CHECK (quantity > 0 AND unit_price >= 0 AND line_total = quantity * unit_price)");

        Schema::create('repair_estimate_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->unique()->constrained('repair_jobs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('repair_estimate_id')->unique()->constrained('repair_estimates')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('approved_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->json('approval_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('approved_at', 6);
        });

        Schema::create('repair_part_consumptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('repair_job_id')->constrained('repair_jobs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('repair_estimate_line_id')->unique()->constrained('repair_estimate_lines')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('quantity');
            $table->foreignId('stock_movement_id')->unique()->constrained('stock_movements')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('consumed_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('consumed_at', 6);
            $table->index(['repair_job_id', 'product_id'], 'mt216_repair_part_job');
        });
        DB::statement('ALTER TABLE repair_part_consumptions ADD CONSTRAINT mt216_repair_part_quantity CHECK (quantity > 0)');

        Schema::create('repair_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->constrained('repair_jobs')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('tender_allocation_id')->unique()->constrained('pos_tender_allocations')->restrictOnDelete()->restrictOnUpdate();
            $table->decimal('amount', 19, 2);
            $table->dateTime('created_at', 6);
            $table->index(['repair_job_id', 'created_at'], 'mt216_repair_payment_job');
        });
        DB::statement('ALTER TABLE repair_payment_links ADD CONSTRAINT mt216_repair_payment_amount CHECK (amount > 0)');

        Schema::create('repair_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->constrained('repair_jobs')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('sequence');
            $table->string('event_type', 50)->collation('utf8mb4_bin');
            $table->string('status', 40)->collation('utf8mb4_bin');
            $table->foreignId('actor_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->string('actor_name', 255);
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('created_at', 6);
            $table->unique(['repair_job_id', 'sequence'], 'mt216_repair_event_sequence');
        });
    }

    public function down(): void
    {
        foreach (['repair_events', 'repair_payment_links', 'repair_part_consumptions', 'repair_estimate_approvals',
            'repair_estimate_lines', 'repair_estimates', 'repair_jobs', 'repair_settings'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Paid repair rollback requires empty repair configuration and history.');
            }
        }
        Schema::dropIfExists('repair_events');
        Schema::dropIfExists('repair_payment_links');
        Schema::dropIfExists('repair_part_consumptions');
        Schema::dropIfExists('repair_estimate_approvals');
        Schema::dropIfExists('repair_estimate_lines');
        Schema::dropIfExists('repair_estimates');
        Schema::dropIfExists('repair_jobs');
        Schema::dropIfExists('repair_settings');
    }
};
