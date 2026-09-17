<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_manifests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('backup_record_id')->unique();
            $table->string('contract_version', 60)->collation('utf8mb4_bin');
            $table->string('key_id', 128)->collation('utf8mb4_bin');
            $table->char('schema_sha256', 64)->collation('utf8mb4_bin');
            $table->char('code_sha256', 64)->collation('utf8mb4_bin');
            $table->char('manifest_sha256', 64)->collation('utf8mb4_bin');
            $table->json('manifest');
            $table->dateTime('verified_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('backup_record_id', 'mt33_fk_manifest_backup')
                ->references('id')->on('backup_records')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('configuration_recovery_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->string('scope', 40)->default('shared')->collation('utf8mb4_bin');
            $table->string('key_id', 128)->collation('utf8mb4_bin');
            $table->char('schema_sha256', 64)->collation('utf8mb4_bin');
            $table->char('code_sha256', 64)->collation('utf8mb4_bin');
            $table->char('manifest_sha256', 64)->collation('utf8mb4_bin');
            $table->json('manifest');
            $table->unsignedBigInteger('created_by_admin_id');
            $table->dateTime('verified_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('created_by_admin_id', 'mt33_fk_config_recovery_admin')
                ->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['scope', 'created_at'], 'mt33_config_recovery_scope');
        });

        Schema::create('backup_restore_rehearsals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->unsignedBigInteger('backup_record_id');
            $table->string('status', 20)->collation('utf8mb4_bin');
            $table->string('failure_code', 80)->collation('utf8mb4_bin')->nullable();
            $table->string('environment', 40)->collation('utf8mb4_bin');
            $table->string('database_name', 120)->collation('utf8mb4_bin');
            $table->string('key_id', 128)->collation('utf8mb4_bin');
            $table->char('manifest_sha256', 64)->collation('utf8mb4_bin')->nullable();
            $table->unsignedBigInteger('checked_by_admin_id');
            $table->dateTime('checked_at', 6);
            $table->json('result')->nullable();
            $table->timestamps(6);
            $table->foreign('backup_record_id', 'mt33_fk_restore_backup')
                ->references('id')->on('backup_records')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('checked_by_admin_id', 'mt33_fk_restore_admin')
                ->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['backup_record_id', 'checked_at'], 'mt33_restore_history');
            $table->index(['status', 'checked_at'], 'mt33_restore_status');
        });

        DB::statement("ALTER TABLE backup_restore_rehearsals ADD CONSTRAINT mt33_restore_status_check CHECK (status IN ('passed','failed'))");
    }

    public function down(): void
    {
        abort_if(
            DB::table('backup_manifests')->exists()
            || DB::table('configuration_recovery_snapshots')->exists()
            || DB::table('backup_restore_rehearsals')->exists(),
            409,
            'Operational recovery evidence must be preserved before rollback.'
        );

        Schema::dropIfExists('backup_restore_rehearsals');
        Schema::dropIfExists('configuration_recovery_snapshots');
        Schema::dropIfExists('backup_manifests');
    }
};
