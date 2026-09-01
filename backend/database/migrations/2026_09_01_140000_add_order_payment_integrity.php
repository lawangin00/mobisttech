<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->char('owner_scope_hash', 64)->collation('utf8mb4_bin')->nullable()->after('customer_id')->index();
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->char('intent_hash', 64)->collation('utf8mb4_bin')->nullable()->after('attempt_key');
            $table->string('failure_code', 100)->collation('utf8mb4_bin')->nullable()->after('intent_hash');
            $table->dateTime('reconciliation_required_at', 6)->nullable()->after('failure_code');
            $table->dateTime('completed_at', 6)->nullable()->after('reconciliation_required_at');
        });
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->enum('outcome', ['paid', 'failed', 'unknown'])->after('payload_hash');
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->dateTime('completed_at', 6)->nullable()->after('evidence_hash');
            $table->dateTime('failed_at', 6)->nullable()->after('completed_at');
        });
        Schema::table('project_milestone_identities', function (Blueprint $table) {
            $table->foreignId('paid_payment_id')->nullable()->unique()->after('snapshot_sha256')->constrained('payments')->restrictOnDelete();
            $table->dateTime('paid_at', 6)->nullable()->after('paid_payment_id');
        });
        DB::statement("ALTER TABLE payments ADD CONSTRAINT mt27_payment_completion CHECK ((status IN ('paid','paid_reconciliation') AND completed_at IS NOT NULL) OR (status NOT IN ('paid','paid_reconciliation') AND completed_at IS NULL))");
        DB::statement("ALTER TABLE refunds ADD CONSTRAINT mt27_refund_terminal CHECK ((status = 'completed' AND completed_at IS NOT NULL AND failed_at IS NULL) OR (status = 'failed' AND failed_at IS NOT NULL AND completed_at IS NULL) OR (status IN ('pending','unknown') AND completed_at IS NULL AND failed_at IS NULL))");
        DB::statement('ALTER TABLE project_milestone_identities ADD CONSTRAINT mt27_milestone_paid_pair CHECK ((paid_payment_id IS NULL) = (paid_at IS NULL))');
    }

    public function down(): void
    {
        if (DB::table('payments')->whereNotNull('intent_hash')->orWhereNotNull('completed_at')->exists()
            || DB::table('refunds')->whereNotNull('completed_at')->orWhereNotNull('failed_at')->exists()
            || DB::table('project_milestone_identities')->whereNotNull('paid_payment_id')->exists()) {
            throw new RuntimeException('Order/payment rollback requires empty MT-2.7 transaction evidence.');
        }
        DB::statement('ALTER TABLE project_milestone_identities DROP CHECK mt27_milestone_paid_pair');
        DB::statement('ALTER TABLE refunds DROP CHECK mt27_refund_terminal');
        DB::statement('ALTER TABLE payments DROP CHECK mt27_payment_completion');
        Schema::table('project_milestone_identities', function (Blueprint $table) {
            $table->dropForeign(['paid_payment_id']);
            $table->dropColumn(['paid_payment_id', 'paid_at']);
        });
        Schema::table('refunds', fn (Blueprint $table) => $table->dropColumn(['completed_at', 'failed_at']));
        Schema::table('payment_receipts', fn (Blueprint $table) => $table->dropColumn('outcome'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['intent_hash', 'failure_code', 'reconciliation_required_at', 'completed_at']));
        if (Schema::hasColumn('orders', 'owner_scope_hash')) {
            Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('owner_scope_hash'));
        }
    }
};
