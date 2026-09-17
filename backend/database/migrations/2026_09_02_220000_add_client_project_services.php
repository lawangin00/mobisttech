<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->string('reference', 80)->collation('utf8mb4_bin')->unique();
            $table->foreignId('service_request_id')->unique()->constrained('service_requests')->restrictOnDelete();
            $table->foreignId('digital_service_id')->nullable()->constrained('digital_services')->restrictOnDelete();
            $table->foreignId('customer_account_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->string('title', 255);
            $table->enum('status', ['request', 'discussion', 'proposal', 'approved', 'in_progress', 'review', 'delivered', 'completed', 'closed'])->default('request');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps(6);
            $table->index(['status', 'updated_at']);
            $table->index(['customer_account_id', 'status']);
        });

        Schema::create('project_proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->foreignId('client_project_id')->constrained('client_projects')->restrictOnDelete();
            $table->unsignedInteger('revision_no');
            $table->enum('state', ['draft', 'approved', 'superseded', 'expired', 'rejected'])->default('draft');
            $table->string('title', 255);
            $table->decimal('amount', 19, 2);
            $table->char('currency', 3)->collation('utf8mb4_bin')->default('PKR');
            $table->dateTime('valid_until', 6);
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->foreignId('quote_id')->nullable()->unique()->constrained('project_quotes')->restrictOnDelete();
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->dateTime('approved_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->unique(['client_project_id', 'revision_no']);
            $table->index(['client_project_id', 'state', 'created_at']);
        });
        DB::statement("ALTER TABLE project_proposals ADD CONSTRAINT mt36_proposal_amount CHECK (amount > 0 AND currency = 'PKR')");
        DB::statement('ALTER TABLE project_proposals ADD CONSTRAINT mt36_proposal_approval_pair CHECK ((state = \'approved\' AND quote_id IS NOT NULL AND approved_by_admin_id IS NOT NULL AND approved_at IS NOT NULL) OR state <> \'approved\')');

        Schema::create('project_proposal_milestones', function (Blueprint $table) {
            $table->uuid('milestone_id')->collation('utf8mb4_bin')->primary();
            $table->foreignId('project_proposal_id')->constrained('project_proposals')->restrictOnDelete();
            $table->enum('kind', ['deposit', 'milestone', 'final']);
            $table->string('label', 190);
            $table->dateTime('due_at', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->foreign('milestone_id')->references('id')->on('project_milestone_identities')->restrictOnDelete();
            $table->index(['project_proposal_id', 'created_at']);
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->uuid('id')->collation('utf8mb4_bin')->primary();
            $table->foreignId('client_project_id')->constrained('client_projects')->restrictOnDelete();
            $table->foreignId('project_proposal_id')->nullable()->constrained('project_proposals')->restrictOnDelete();
            $table->enum('file_type', ['reference', 'delivery']);
            $table->string('object_key', 255)->collation('utf8mb4_bin')->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->collation('utf8mb4_bin');
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64)->collation('utf8mb4_bin');
            $table->foreignId('uploaded_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->foreignId('uploaded_by_customer_account_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('retention_until', 6)->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['client_project_id', 'file_type', 'created_at']);
        });
        DB::statement('ALTER TABLE project_files ADD CONSTRAINT mt36_project_file_size CHECK (byte_size > 0 AND byte_size <= 10485760)');
        DB::statement('ALTER TABLE project_files ADD CONSTRAINT mt36_project_file_uploader CHECK ((uploaded_by_admin_id IS NULL) <> (uploaded_by_customer_account_id IS NULL))');

        Schema::create('project_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_project_id')->constrained('client_projects')->restrictOnDelete();
            $table->string('event_type', 80)->collation('utf8mb4_bin');
            $table->foreignId('actor_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->foreignId('actor_customer_account_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64)->collation('utf8mb4_bin');
            $table->dateTime('occurred_at', 6);
            $table->index(['client_project_id', 'occurred_at']);
        });
        DB::statement('ALTER TABLE project_events ADD CONSTRAINT mt36_project_event_actor CHECK (NOT (actor_admin_id IS NOT NULL AND actor_customer_account_id IS NOT NULL))');
    }

    public function down(): void
    {
        foreach (['project_events', 'project_files', 'project_proposal_milestones', 'project_proposals', 'client_projects'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Client project rollback requires empty MT-3.6 tables.');
            }
        }        foreach (['project_events', 'project_files', 'project_proposal_milestones', 'project_proposals', 'client_projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
