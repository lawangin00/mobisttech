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
            $table->unique(['id', 'invoice_id', 'product_id', 'outlet_id'], 'mt26_sales_claim_owner');
        });

        Schema::table('claims', function (Blueprint $table) {
            $table->json('warranty_snapshot')->nullable()->after('handled_by_name');
            $table->dateTime('warranty_expires_at', 6)->nullable()->after('warranty_snapshot');
            $table->unsignedBigInteger('active_stock_unit_id')->nullable()->storedAs("CASE WHEN status IN ('received','diagnosing','awaiting_parts','repaired','replaced','ready_for_collection') THEN stock_unit_id ELSE NULL END");
            $table->unique('active_stock_unit_id', 'mt26_claim_active_unit');
            $table->foreign(['sale_id', 'invoice_id', 'product_id', 'outlet_id'], 'mt26_fk_claim_sale_owner')
                ->references(['id', 'invoice_id', 'product_id', 'outlet_id'])->on('sales')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement("ALTER TABLE claims ADD CONSTRAINT mt26_claim_status CHECK (status IN ('received','diagnosing','awaiting_parts','repaired','replaced','rejected','ready_for_collection','delivered','closed')), ADD CONSTRAINT mt26_claim_unit_shape CHECK ((stock_unit_id IS NULL) OR quantity = 1)");

        Schema::create('claim_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('claim_id')->constrained('claims')->restrictOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status', 40)->collation('utf8mb4_bin');
            $table->text('note');
            $table->string('actor_type', 40)->collation('utf8mb4_bin')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->dateTime('occurred_at', 6);
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->unique(['claim_id', 'sequence']);
            $table->index(['claim_id', 'occurred_at']);
        });
        DB::statement("ALTER TABLE claim_events ADD CONSTRAINT mt26_claim_event_sequence CHECK (sequence > 0), ADD CONSTRAINT mt26_claim_event_status CHECK (status IN ('received','diagnosing','awaiting_parts','repaired','replaced','rejected','ready_for_collection','delivered','closed')), ADD CONSTRAINT mt26_claim_event_actor CHECK ((actor_type IS NULL) = (actor_id IS NULL))");
    }

    public function down(): void
    {
        if (DB::table('claim_events')->exists() || DB::table('claims')->exists()) {
            throw new RuntimeException('Warranty rollback requires empty claim history.');
        }
        Schema::dropIfExists('claim_events');
        DB::statement('ALTER TABLE claims DROP CHECK mt26_claim_unit_shape, DROP CHECK mt26_claim_status');
        Schema::table('claims', function (Blueprint $table) {
            $table->dropForeign('mt26_fk_claim_sale_owner');
            $table->dropUnique('mt26_claim_active_unit');
            $table->dropColumn(['warranty_snapshot', 'warranty_expires_at', 'active_stock_unit_id']);
        });
        Schema::table('sales', fn (Blueprint $table) => $table->dropUnique('mt26_sales_claim_owner'));
    }
};
