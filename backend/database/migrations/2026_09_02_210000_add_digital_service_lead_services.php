<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_service_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('digital_service_id')->constrained('digital_services')->restrictOnDelete();
            $table->string('code', 80)->collation('utf8mb4_bin');
            $table->string('name', 190);
            $table->enum('pricing_type', ['fixed', 'starting_from', 'quote']);
            $table->decimal('price', 19, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps(6);
            $table->unique(['digital_service_id', 'code']);
        });

        Schema::create('digital_service_addons', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('digital_service_id')->constrained('digital_services')->restrictOnDelete();
            $table->string('code', 80)->collation('utf8mb4_bin');
            $table->string('name', 190);
            $table->enum('pricing_type', ['fixed', 'starting_from', 'quote']);
            $table->decimal('price', 19, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps(6);
            $table->unique(['digital_service_id', 'code']);
        });

        Schema::create('digital_consultation_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->string('timezone', 64)->default('Asia/Karachi');
            $table->json('weekly_availability')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignId('updated_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestamps(6);
        });
        DB::statement('ALTER TABLE digital_consultation_settings ADD CONSTRAINT digital_consultation_singleton CHECK (id = 1)');

        Schema::create('service_request_rate_buckets', function (Blueprint $table) {
            $table->char('submission_fingerprint', 64)->collation('utf8mb4_bin');
            $table->dateTime('bucket_start', 6);
            $table->unsignedTinyInteger('request_count')->default(0);
            $table->primary(['submission_fingerprint', 'bucket_start']);
        });

        Schema::create('service_request_details', function (Blueprint $table) {
            $table->foreignId('service_request_id')->primary()->constrained('service_requests')->restrictOnDelete();
            $table->string('project_type', 120)->nullable();
            $table->string('existing_url', 500)->nullable();
            $table->string('budget_range', 120)->nullable();
            $table->string('preferred_timeline', 190)->nullable();
            $table->string('source', 120)->nullable();
            $table->string('campaign', 120)->nullable();
            $table->char('submission_fingerprint', 64)->collation('utf8mb4_bin');
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->dateTime('follow_up_at', 6)->nullable();
            $table->dateTime('last_contacted_at', 6)->nullable();
            $table->dateTime('closed_at', 6)->nullable();
            $table->string('preferred_timezone', 64)->nullable();
            $table->dateTime('preferred_window_start_utc', 6)->nullable();
            $table->dateTime('preferred_window_end_utc', 6)->nullable();
            $table->boolean('consultation_requested')->default(false);
            $table->enum('consultation_status', ['not_requested', 'requested', 'confirmed', 'completed', 'cancelled'])->default('not_requested');
            $table->timestamps(6);
            $table->index(['submission_fingerprint', 'created_at']);
            $table->index(['assigned_admin_id', 'follow_up_at']);
        });

        Schema::create('service_request_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained('service_requests')->restrictOnDelete();
            $table->enum('selection_type', ['package', 'addon']);
            $table->unsignedBigInteger('source_id');
            $table->string('label_snapshot', 190);
            $table->enum('pricing_type', ['fixed', 'starting_from', 'quote']);
            $table->decimal('price_snapshot', 19, 2)->nullable();
            $table->char('currency', 3)->collation('utf8mb4_bin')->default('PKR');
            $table->unsignedBigInteger('source_version');
            $table->dateTime('created_at', 6);
            $table->unique(['service_request_id', 'selection_type', 'source_id'], 'mt35_request_selection_unique');
        });

        Schema::create('service_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_request_id')->constrained('service_requests')->restrictOnDelete();
            $table->string('event_type', 80)->collation('utf8mb4_bin');
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('occurred_at', 6);
            $table->index(['service_request_id', 'occurred_at']);
        });

        Schema::create('service_request_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('service_request_id')->constrained('service_requests')->restrictOnDelete();
            $table->string('object_key', 255)->collation('utf8mb4_bin')->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->collation('utf8mb4_bin');
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('uploaded_at', 6);
            $table->index(['service_request_id', 'uploaded_at']);
        });

        DB::statement("ALTER TABLE digital_service_packages ADD CONSTRAINT digital_service_package_price CHECK ((pricing_type = 'quote' AND price IS NULL) OR (pricing_type <> 'quote' AND price IS NOT NULL AND price >= 0))");
        DB::statement("ALTER TABLE digital_service_addons ADD CONSTRAINT digital_service_addon_price CHECK ((pricing_type = 'quote' AND price IS NULL) OR (pricing_type <> 'quote' AND price IS NOT NULL AND price >= 0))");
        DB::statement("ALTER TABLE service_request_selections ADD CONSTRAINT service_request_selection_price CHECK (currency = 'PKR' AND ((pricing_type = 'quote' AND price_snapshot IS NULL) OR (pricing_type <> 'quote' AND price_snapshot IS NOT NULL AND price_snapshot >= 0)))");
        DB::statement('ALTER TABLE service_request_details ADD CONSTRAINT service_request_window_shape CHECK ((preferred_window_start_utc IS NULL AND preferred_window_end_utc IS NULL) OR (preferred_window_start_utc IS NOT NULL AND preferred_window_end_utc IS NOT NULL AND preferred_window_end_utc > preferred_window_start_utc))');
    }

    public function down(): void
    {
        foreach (['service_request_files', 'service_request_events', 'service_request_selections', 'service_request_details', 'service_request_rate_buckets', 'digital_consultation_settings', 'digital_service_addons', 'digital_service_packages'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Digital service lead rollback requires empty MT-3.5 tables.');
            }
        }
        foreach (['service_request_files', 'service_request_events', 'service_request_selections', 'service_request_details', 'service_request_rate_buckets', 'digital_consultation_settings', 'digital_service_addons', 'digital_service_packages'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
