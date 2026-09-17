<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reset_operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->string('level', 20)->collation('utf8mb4_bin');
            $table->json('selected_domains');
            $table->char('scope_sha256', 64)->collation('utf8mb4_bin');
            $table->char('preview_sha256', 64)->collation('utf8mb4_bin');
            $table->json('preview');
            $table->unsignedBigInteger('record_count')->default(0);
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedBigInteger('actor_admin_id');
            $table->string('status', 30)->collation('utf8mb4_bin')->default('previewed');
            $table->unsignedBigInteger('backup_record_id')->nullable();
            $table->json('object_backup_manifest')->nullable();
            $table->string('failure_code', 80)->collation('utf8mb4_bin')->nullable();
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->json('result')->nullable();
            $table->timestamps(6);
            $table->foreign('actor_admin_id', 'mt38_fk_reset_actor')
                ->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('backup_record_id', 'mt38_fk_reset_backup')
                ->references('id')->on('backup_records')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['level', 'created_at'], 'mt38_reset_level_history');
            $table->index(['status', 'created_at'], 'mt38_reset_status_history');
        });

        DB::statement("ALTER TABLE reset_operations ADD CONSTRAINT mt38_reset_level_check CHECK (level IN ('transactional','business','factory'))");
        DB::statement("ALTER TABLE reset_operations ADD CONSTRAINT mt38_reset_status_check CHECK (status IN ('previewed','preparing','backup_verified','deleting','cleanup_pending','completed','failed'))");
    }

    public function down(): void
    {
        abort_if(DB::table('reset_operations')->exists(), 409,
            'Reset audit evidence must be preserved before rollback.');
        Schema::dropIfExists('reset_operations');
    }
};
