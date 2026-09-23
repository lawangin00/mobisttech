<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Multiple not-yet-initiated payments may have NULL references.
        // A non-NULL gateway reference must never identify two owned payments,
        // including across merchant/mode rotations or independent outlets.
        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['gateway', 'gateway_order_reference'], 'mt75_payments_gateway_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('mt75_payments_gateway_reference_unique');
        });
    }
};
