<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('business_name', 255);
            $table->string('business_email', 255);
            $table->string('public_website', 255);
            $table->unsignedBigInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by_admin_id')->nullable();
            $table->timestamps(6);
            $table->foreign('updated_by_admin_id', 'mt218_fk_business_profile_admin')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::table('business_profiles')->insert([
            'id' => 1, 'business_name' => 'mobiST Technologies', 'business_email' => 'mobisttech@gmail.com',
            'public_website' => 'https://mobisttech.com', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('admin_identity_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('source_repository', 40)->collation('utf8mb4_bin');
            $table->string('source_table', 80)->collation('utf8mb4_bin');
            $table->string('source_primary_key', 120)->collation('utf8mb4_bin');
            $table->unsignedBigInteger('admin_id');
            $table->json('permission_snapshot');
            $table->char('source_credential_sha256', 64)->collation('utf8mb4_bin');
            $table->string('decision', 30)->default('mapped')->collation('utf8mb4_bin');
            $table->text('reason');
            $table->unsignedBigInteger('verified_by_admin_id');
            $table->dateTime('verified_at', 6);
            $table->timestamps(6);
            $table->unique(['source_repository', 'source_table', 'source_primary_key'], 'mt218_admin_source_unique');
            $table->foreign('admin_id', 'mt218_fk_admin_mapping_target')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('verified_by_admin_id', 'mt218_fk_admin_mapping_verifier')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->collation('utf8mb4_bin')->unique();
            $table->string('status', 30)->default('not_connected')->collation('utf8mb4_bin');
            $table->string('account', 255)->nullable();
            $table->json('scopes')->nullable();
            $table->longText('encrypted_credentials')->nullable();
            $table->json('configuration')->nullable();
            $table->unsignedBigInteger('authorized_by_admin_id')->nullable();
            $table->dateTime('authorized_at', 6)->nullable();
            $table->dateTime('last_success_at', 6)->nullable();
            $table->text('last_error_summary')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps(6);
            $table->foreign('authorized_by_admin_id', 'mt218_fk_integration_admin')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['status', 'provider'], 'mt218_integration_status');
        });
        DB::statement("ALTER TABLE integration_connections ADD CONSTRAINT mt218_integration_provider CHECK (provider IN ('gmail','google_drive')), ADD CONSTRAINT mt218_integration_status_check CHECK (status IN ('not_connected','connecting','connected','error'))");
        DB::table('integration_connections')->insert([
            ['provider' => 'gmail', 'status' => 'not_connected', 'configuration' => null, 'created_at' => now(), 'updated_at' => now()],
            ['provider' => 'google_drive', 'status' => 'not_connected', 'configuration' => json_encode(['remote' => 'mobisttech-drive:', 'namespace' => 'mobiST Tech/Backups/'], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('integration_oauth_states', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->collation('utf8mb4_bin');
            $table->char('state_sha256', 64)->collation('utf8mb4_bin')->unique();
            $table->text('encrypted_code_verifier');
            $table->unsignedBigInteger('admin_id');
            $table->dateTime('expires_at', 6);
            $table->dateTime('consumed_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('admin_id', 'mt218_fk_oauth_state_admin')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['provider', 'expires_at'], 'mt218_oauth_expiry');
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('integration_connection_id');
            $table->string('action', 60)->collation('utf8mb4_bin');
            $table->string('outcome', 30)->collation('utf8mb4_bin');
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('safe_reference', 255)->nullable();
            $table->dateTime('occurred_at', 6);
            $table->foreign('integration_connection_id', 'mt218_fk_event_connection')->references('id')->on('integration_connections')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('admin_id', 'mt218_fk_event_admin')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['integration_connection_id', 'occurred_at'], 'mt218_event_history');
        });

        Schema::table('backup_records', function (Blueprint $table) {
            $table->unsignedBigInteger('integration_connection_id')->nullable()->after('outlet_id');
            $table->foreign('integration_connection_id', 'mt218_fk_backup_integration')->references('id')->on('integration_connections')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        abort_if(DB::table('admin_identity_mappings')->exists() || DB::table('integration_events')->exists()
            || DB::table('integration_oauth_states')->exists()
            || DB::table('backup_records')->whereNotNull('integration_connection_id')->exists(), 409,
            'Unified Admin or integration evidence must be preserved before rollback.');
        Schema::table('backup_records', function (Blueprint $table) {
            $table->dropForeign('mt218_fk_backup_integration');
            $table->dropColumn('integration_connection_id');
        });
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('integration_oauth_states');
        Schema::dropIfExists('integration_connections');
        Schema::dropIfExists('admin_identity_mappings');
        Schema::dropIfExists('business_profiles');
    }
};
