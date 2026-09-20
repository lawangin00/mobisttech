<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_offline_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->restrictOnDelete();
            $table->uuid('batch_id')->collation('utf8mb4_bin');
            $table->char('code_digest', 64)->collation('utf8mb4_bin')->unique();
            $table->dateTime('issued_at', 6);
            $table->dateTime('used_at', 6)->nullable();
            $table->dateTime('revoked_at', 6)->nullable();
            $table->index(['admin_id', 'used_at'], 'owner_recovery_admin_used');
        });
    }

    public function down(): void
    {
        if (DB::table('owner_offline_recovery_codes')->exists()) {
            throw new RuntimeException('Retained owner recovery history must not be silently dropped.');
        }
        Schema::dropIfExists('owner_offline_recovery_codes');
    }
};
