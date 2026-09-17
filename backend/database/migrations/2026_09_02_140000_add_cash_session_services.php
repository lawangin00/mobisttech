<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedBigInteger('open_outlet_guard')->nullable()->unique();
            $table->foreignId('opened_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('closed_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('variance_approved_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->date('business_date');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedInteger('version')->default(1);
            $table->decimal('opening_cash', 19, 2);
            $table->decimal('expected_cash', 19, 2)->nullable();
            $table->decimal('actual_cash', 19, 2)->nullable();
            $table->decimal('variance_amount', 19, 2)->nullable();
            $table->text('variance_reason')->nullable();
            $table->longText('closing_snapshot')->nullable();
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin')->nullable();
            $table->dateTime('opened_at', 6);
            $table->dateTime('closed_at', 6)->nullable();
            $table->timestamps(6);
            $table->unique(['id', 'outlet_id'], 'mt212_cash_session_id_outlet');
            $table->index(['outlet_id', 'business_date', 'status'], 'mt212_cash_session_day');
            $table->index(['opened_by_admin_id', 'opened_at'], 'mt212_cash_session_operator');
        });
        DB::statement('ALTER TABLE cash_sessions ADD CONSTRAINT mt212_cash_opening CHECK (opening_cash >= 0)');
        DB::statement("ALTER TABLE cash_sessions ADD CONSTRAINT mt212_cash_state CHECK ((status = 'open' AND open_outlet_guard = outlet_id AND closed_at IS NULL AND expected_cash IS NULL AND actual_cash IS NULL AND variance_amount IS NULL AND closing_snapshot IS NULL AND snapshot_sha256 IS NULL) OR (status = 'closed' AND open_outlet_guard IS NULL AND closed_at IS NOT NULL AND expected_cash IS NOT NULL AND actual_cash IS NOT NULL AND variance_amount = actual_cash - expected_cash AND closing_snapshot IS NOT NULL AND snapshot_sha256 IS NOT NULL))");

        Schema::create('cash_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('cash_session_id');
            $table->foreignId('outlet_id');
            $table->enum('type', ['cash_in', 'expense', 'payout']);
            $table->decimal('amount', 19, 2);
            $table->string('reason', 500);
            $table->string('reference', 120)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->text('review_notes')->nullable();
            $table->dateTime('reviewed_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign(['cash_session_id', 'outlet_id'], 'mt212_fk_entry_session')
                ->references(['id', 'outlet_id'])->on('cash_sessions')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['cash_session_id', 'status', 'type'], 'mt212_cash_entry_session');
            $table->index(['outlet_id', 'created_at'], 'mt212_cash_entry_outlet_time');
        });
        DB::statement('ALTER TABLE cash_entries ADD CONSTRAINT mt212_cash_entry_amount CHECK (amount > 0)');
        DB::statement("ALTER TABLE cash_entries ADD CONSTRAINT mt212_cash_entry_review CHECK ((status = 'pending' AND reviewed_by_admin_id IS NULL AND reviewed_at IS NULL) OR (status IN ('approved','rejected') AND reviewed_by_admin_id IS NOT NULL AND reviewed_at IS NOT NULL))");

        Schema::table('pos_tender_allocations', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('outlet_id');
            $table->foreign(['cash_session_id', 'outlet_id'], 'mt212_fk_tender_cash_session')
                ->references(['id', 'outlet_id'])->on('cash_sessions')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['cash_session_id', 'method', 'created_at'], 'mt212_tender_session_method');
        });
        Schema::table('pos_refund_allocations', function (Blueprint $table) {
            $table->foreignId('cash_session_id')->nullable()->after('outlet_id');
            $table->foreign(['cash_session_id', 'outlet_id'], 'mt212_fk_refund_cash_session')
                ->references(['id', 'outlet_id'])->on('cash_sessions')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['cash_session_id', 'refund_method', 'recorded_at'], 'mt212_refund_session_method');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cash_entries') && DB::table('cash_entries')->exists()) {
            throw new RuntimeException('Cash-session rollback requires empty cash entries.');
        }
        if (Schema::hasTable('cash_sessions') && DB::table('cash_sessions')->exists()) {
            throw new RuntimeException('Cash-session rollback requires empty cash sessions.');
        }
        if (Schema::hasColumn('pos_tender_allocations', 'cash_session_id')
            && DB::table('pos_tender_allocations')->whereNotNull('cash_session_id')->exists()) {
            throw new RuntimeException('Cash-session rollback requires unassigned POS tenders.');
        }
        if (Schema::hasColumn('pos_refund_allocations', 'cash_session_id')
            && DB::table('pos_refund_allocations')->whereNotNull('cash_session_id')->exists()) {
            throw new RuntimeException('Cash-session rollback requires unassigned POS refunds.');
        }
        Schema::table('pos_refund_allocations', function (Blueprint $table) {
            $table->dropForeign('mt212_fk_refund_cash_session');
            $table->dropIndex('mt212_fk_refund_cash_session');
            $table->dropIndex('mt212_refund_session_method');
            $table->dropColumn('cash_session_id');
        });
        Schema::table('pos_tender_allocations', function (Blueprint $table) {
            $table->dropForeign('mt212_fk_tender_cash_session');
            $table->dropIndex('mt212_fk_tender_cash_session');
            $table->dropIndex('mt212_tender_session_method');
            $table->dropColumn('cash_session_id');
        });
        Schema::dropIfExists('cash_entries');
        Schema::dropIfExists('cash_sessions');
    }
};
