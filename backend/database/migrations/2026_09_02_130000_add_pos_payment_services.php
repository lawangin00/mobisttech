<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'config.payments.manage' => 'Manage POS payment destinations',
        'shop.payments.reconcile' => 'Reconcile POS payment settlements',
        'shop.payments.refund-override' => 'Override POS refund destination or method',
        'shop.payments.refund-approve' => 'Approve POS refund overrides',
    ];

    public function up(): void
    {
        Schema::create('pos_payment_destinations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->enum('method', ['cash', 'card', 'mobile_wallet', 'bank_transfer']);
            $table->string('display_name', 120);
            $table->string('provider_label', 120)->nullable();
            $table->string('masked_identifier', 120)->nullable();
            $table->boolean('active')->default(true);
            $table->dateTime('effective_from', 6)->nullable();
            $table->dateTime('effective_until', 6)->nullable();
            $table->boolean('requires_refund_override_approval')->default(true);
            $table->text('internal_notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps(6);
            $table->unique(['id', 'outlet_id'], 'mt220_destination_id_outlet');
            $table->unique(['outlet_id', 'method', 'display_name'], 'mt220_destination_label');
            $table->index(['outlet_id', 'active', 'method'], 'mt220_destination_active');
        });
        DB::statement('ALTER TABLE pos_payment_destinations ADD CONSTRAINT mt220_destination_effective CHECK (effective_until IS NULL OR effective_from IS NULL OR effective_until > effective_from)');

        Schema::create('pos_tender_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('invoice_id');
            $table->foreignId('outlet_id');
            $table->foreignId('payment_destination_id');
            $table->unsignedInteger('sequence');
            $table->enum('method', ['cash', 'card', 'mobile_wallet', 'bank_transfer']);
            $table->decimal('amount', 19, 2);
            $table->decimal('cash_tendered', 19, 2)->nullable();
            $table->decimal('change_returned', 19, 2)->default('0.00');
            $table->string('transaction_reference', 120)->collation('utf8mb4_bin')->nullable();
            $table->string('reconciliation_reference', 120)->collation('utf8mb4_bin')->nullable();
            $table->longText('destination_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->enum('reconciliation_state', ['cash', 'pending', 'confirmed', 'variance']);
            $table->unsignedInteger('settlement_version')->default(0);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->timestamps(6);
            $table->foreign(['invoice_id', 'outlet_id'], 'mt220_fk_tender_invoice')
                ->references(['id', 'outlet_id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['payment_destination_id', 'outlet_id'], 'mt220_fk_tender_destination')
                ->references(['id', 'outlet_id'])->on('pos_payment_destinations')->restrictOnDelete()->restrictOnUpdate();
            $table->unique(['invoice_id', 'sequence'], 'mt220_tender_invoice_sequence');
            $table->index(['outlet_id', 'method', 'created_at'], 'mt220_tender_outlet_method');
            $table->index(['payment_destination_id', 'reconciliation_state'], 'mt220_tender_reconciliation');
        });
        DB::statement('ALTER TABLE pos_tender_allocations ADD CONSTRAINT mt220_tender_amount CHECK (amount > 0)');
        DB::statement("ALTER TABLE pos_tender_allocations ADD CONSTRAINT mt220_tender_cash CHECK ((method = 'cash' AND cash_tendered IS NOT NULL AND cash_tendered >= amount AND change_returned = cash_tendered - amount AND reconciliation_state = 'cash') OR (method <> 'cash' AND cash_tendered IS NULL AND change_returned = 0 AND reconciliation_state <> 'cash'))");

        Schema::create('pos_settlement_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('tender_allocation_id')->constrained('pos_tender_allocations')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('sequence');
            $table->decimal('gross_amount', 19, 2);
            $table->decimal('fee_amount', 19, 2)->default('0.00');
            $table->decimal('adjustment_amount', 19, 2)->default('0.00');
            $table->decimal('expected_net_amount', 19, 2);
            $table->decimal('received_net_amount', 19, 2);
            $table->decimal('variance_amount', 19, 2);
            $table->string('external_reference', 120)->collation('utf8mb4_bin')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->longText('event_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('recorded_at', 6);
            $table->timestamps(6);
            $table->unique(['tender_allocation_id', 'sequence'], 'mt220_settlement_sequence');
            $table->index(['recorded_by_admin_id', 'recorded_at'], 'mt220_settlement_actor_time');
        });
        DB::statement('ALTER TABLE pos_settlement_events ADD CONSTRAINT mt220_settlement_amounts CHECK (gross_amount > 0 AND fee_amount >= 0 AND received_net_amount >= 0 AND expected_net_amount = gross_amount - fee_amount + adjustment_amount AND variance_amount = received_net_amount - expected_net_amount)');

        Schema::create('pos_refund_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('return_id')->constrained('returns')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('invoice_id');
            $table->foreignId('outlet_id');
            $table->foreignId('original_tender_allocation_id')->constrained('pos_tender_allocations')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('refund_destination_id');
            $table->decimal('amount', 19, 2);
            $table->enum('original_method', ['cash', 'card', 'mobile_wallet', 'bank_transfer']);
            $table->enum('refund_method', ['cash', 'card', 'mobile_wallet', 'bank_transfer']);
            $table->boolean('is_override')->default(false);
            $table->text('override_reason')->nullable();
            $table->foreignId('requested_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->string('transaction_reference', 120)->collation('utf8mb4_bin')->nullable();
            $table->longText('original_tender_snapshot');
            $table->longText('refund_destination_snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('recorded_at', 6);
            $table->timestamps(6);
            $table->foreign(['invoice_id', 'outlet_id'], 'mt220_fk_refund_invoice')
                ->references(['id', 'outlet_id'])->on('invoices')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign(['refund_destination_id', 'outlet_id'], 'mt220_fk_refund_destination')
                ->references(['id', 'outlet_id'])->on('pos_payment_destinations')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['return_id', 'recorded_at'], 'mt220_refund_return_time');
            $table->index(['original_tender_allocation_id', 'recorded_at'], 'mt220_refund_original_tender');
        });
        DB::statement('ALTER TABLE pos_refund_allocations ADD CONSTRAINT mt220_refund_amount CHECK (amount > 0)');
        DB::statement('ALTER TABLE pos_refund_allocations ADD CONSTRAINT mt220_refund_override CHECK ((is_override = 0 AND override_reason IS NULL) OR (is_override = 1 AND override_reason IS NOT NULL AND CHAR_LENGTH(TRIM(override_reason)) > 0))');

        $now = now();
        foreach (self::PERMISSIONS as $code => $label) {
            DB::table('permission_definitions')->insertOrIgnore(['code' => $code, 'label' => $label, 'introduced_at' => $now]);
        }
        $this->grant('Full Access', array_keys(self::PERMISSIONS));
        $this->grant('Manager', array_keys(self::PERMISSIONS));
        $this->grant('Store Manager', ['shop.payments.reconcile', 'shop.payments.refund-override', 'shop.payments.refund-approve']);
    }

    public function down(): void
    {
        foreach (['pos_refund_allocations', 'pos_settlement_events', 'pos_tender_allocations', 'pos_payment_destinations'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('POS payment rollback requires empty MT-2.20 business tables.');
            }
        }
        DB::table('role_permissions')->whereIn('permission_code', array_keys(self::PERMISSIONS))->delete();
        DB::table('permission_definitions')->whereIn('code', array_keys(self::PERMISSIONS))->delete();
        Schema::dropIfExists('pos_refund_allocations');
        Schema::dropIfExists('pos_settlement_events');
        Schema::dropIfExists('pos_tender_allocations');
        Schema::dropIfExists('pos_payment_destinations');
    }

    private function grant(string $roleName, array $permissions): void
    {
        $roleId = DB::table('roles')->where('name', $roleName)->value('id');
        if (! $roleId) {
            return;
        }
        foreach ($permissions as $permission) {
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_code' => $permission,
            ]);
        }
    }
};
