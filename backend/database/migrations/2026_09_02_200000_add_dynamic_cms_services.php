<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_configuration_revisions', function (Blueprint $table) {
            $table->foreignId('created_by_admin_id')->nullable()->after('created_by_user_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->after('published_by_user_id')->constrained('admins')->restrictOnDelete();
        });
        Schema::table('site_managed_pages', function (Blueprint $table) {
            $table->uuid('public_id')->nullable()->after('id');
            $table->unsignedBigInteger('version')->default(1)->after('public_id');
            $table->string('capability_scope', 20)->default('common')->after('content_purpose');
            $table->json('structured_content')->nullable()->after('capability_scope');
            $table->boolean('protected_slug')->default(false)->after('structured_content');
            $table->foreignId('created_by_admin_id')->nullable()->after('created_by_user_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->after('updated_by_user_id')->constrained('admins')->restrictOnDelete();
            $table->unique('public_id', 'mt32_site_pages_public');
        });
        Schema::table('site_media_assets', function (Blueprint $table) {
            $table->foreignId('uploaded_by_admin_id')->nullable()->after('uploaded_by_user_id')->constrained('admins')->restrictOnDelete();
        });
        Schema::table('site_navigation_items', function (Blueprint $table) {
            $table->string('capability_scope', 20)->default('common')->after('target_behavior');
            $table->foreignId('created_by_admin_id')->nullable()->after('created_by_user_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('updated_by_admin_id')->nullable()->after('updated_by_user_id')->constrained('admins')->restrictOnDelete();
        });
        Schema::table('site_settings', function (Blueprint $table) {
            $table->foreignId('updated_by_admin_id')->nullable()->after('sort_order')->constrained('admins')->restrictOnDelete();
        });

        Schema::create('site_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_managed_page_id')->constrained('site_managed_pages')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 20)->default('draft');
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->foreignId('restored_from_revision_id')->nullable()->constrained('site_page_revisions')->restrictOnDelete();
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['site_managed_page_id', 'version'], 'mt32_page_revision_version');
            $table->index(['site_managed_page_id', 'state'], 'mt32_page_revision_state');
        });
        Schema::table('site_managed_pages', function (Blueprint $table) {
            $table->foreignId('current_revision_id')->nullable()->after('version')->constrained('site_page_revisions')->restrictOnDelete();
        });

        Schema::create('site_page_service_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_managed_page_id')->constrained('site_managed_pages')->restrictOnDelete();
            $table->foreignId('digital_service_id')->constrained('digital_services')->restrictOnDelete();
            $table->string('relationship', 30);
            $table->dateTime('created_at', 6)->nullable();
            $table->unique(['site_managed_page_id', 'digital_service_id', 'relationship'], 'mt32_page_service_unique');
            $table->index(['digital_service_id', 'relationship'], 'mt32_page_service_lookup');
        });

        Schema::create('cms_policies', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('policy_type', 40)->collation('utf8mb4_bin')->unique();
            $table->string('slug', 160)->collation('utf8mb4_bin')->unique();
            $table->string('title', 190);
            $table->string('requirement_state', 20)->default('required');
            $table->string('applicability_state', 20)->default('required');
            $table->string('footer_destination', 80)->nullable();
            $table->string('approval_state', 30)->default('draft');
            $table->string('factual_review_state', 30)->default('pending');
            $table->date('effective_date')->nullable();
            $table->boolean('protected_slug')->default(true);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('updated_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->index(['approval_state', 'factual_review_state'], 'mt32_policy_review');
        });

        Schema::create('cms_policy_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_policy_id')->constrained('cms_policies')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('state', 20)->default('draft');
            $table->longText('content');
            $table->char('content_sha256', 64);
            $table->date('effective_date')->nullable();
            $table->string('approval_state', 30)->default('draft');
            $table->string('factual_review_state', 30)->default('pending');
            $table->json('unresolved_decisions')->nullable();
            $table->string('professional_review_reference', 255)->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->foreignId('restored_from_revision_id')->nullable()->constrained('cms_policy_revisions')->restrictOnDelete();
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['cms_policy_id', 'version'], 'mt32_policy_revision_version');
            $table->index(['cms_policy_id', 'state'], 'mt32_policy_revision_state');
        });
        Schema::table('cms_policies', function (Blueprint $table) {
            $table->foreignId('current_revision_id')->nullable()->after('effective_date')->constrained('cms_policy_revisions')->restrictOnDelete();
        });

        Schema::create('software_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name', 190);
            $table->string('slug', 160)->collation('utf8mb4_bin')->unique();
            $table->string('lifecycle_state', 20)->default('draft');
            $table->boolean('protected_slug')->default(false);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('updated_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->dateTime('archived_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->index(['lifecycle_state'], 'mt32_software_state');
        });

        Schema::create('software_product_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('software_product_id')->constrained('software_products')->restrictOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('state', 20)->default('draft');
            $table->json('snapshot');
            $table->char('snapshot_sha256', 64);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->foreignId('restored_from_revision_id')->nullable()->constrained('software_product_revisions')->restrictOnDelete();
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['software_product_id', 'revision_no'], 'mt32_software_revision_no');
            $table->index(['software_product_id', 'state'], 'mt32_software_revision_state');
        });

        Schema::create('software_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('software_product_id')->constrained('software_products')->restrictOnDelete();
            $table->string('version', 80)->collation('utf8mb4_bin');
            $table->date('release_date');
            $table->string('state', 20)->default('draft');
            $table->text('summary');
            $table->json('notes');
            $table->json('impact_review');
            $table->char('snapshot_sha256', 64);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('published_by_admin_id')->nullable()->constrained('admins')->restrictOnDelete();
            $table->dateTime('published_at', 6)->nullable();
            $table->dateTime('created_at', 6)->nullable();
            $table->dateTime('updated_at', 6)->nullable();
            $table->unique(['software_product_id', 'version'], 'mt32_software_release_version');
            $table->index(['software_product_id', 'state', 'release_date'], 'mt32_software_release_state');
        });
        Schema::table('software_products', function (Blueprint $table) {
            $table->foreignId('current_revision_id')->nullable()->after('protected_slug')->constrained('software_product_revisions')->restrictOnDelete();
            $table->foreignId('current_release_id')->nullable()->after('current_revision_id')->constrained('software_releases')->restrictOnDelete();
        });

        Schema::create('cms_route_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500)->collation('utf8mb4_bin')->unique();
            $table->string('to_path', 500)->collation('utf8mb4_bin');
            $table->foreignId('software_product_id')->nullable()->constrained('software_products')->restrictOnDelete();
            $table->string('reason', 255);
            $table->foreignId('created_by_admin_id')->constrained('admins')->restrictOnDelete();
            $table->dateTime('created_at', 6)->nullable();
            $table->index(['software_product_id'], 'mt32_route_redirect_product');
        });
    }

    public function down(): void
    {
        foreach (['cms_route_redirects', 'software_releases', 'software_product_revisions', 'software_products',
            'cms_policy_revisions', 'cms_policies', 'site_page_service_links', 'site_page_revisions'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('MT-3.2 rollback requires empty CMS history tables.');
            }
        }

        Schema::dropIfExists('cms_route_redirects');
        Schema::table('software_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_release_id');
            $table->dropConstrainedForeignId('current_revision_id');
        });
        Schema::dropIfExists('software_releases');
        Schema::dropIfExists('software_product_revisions');
        Schema::dropIfExists('software_products');
        Schema::table('cms_policies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_revision_id');
        });
        Schema::dropIfExists('cms_policy_revisions');
        Schema::dropIfExists('cms_policies');
        Schema::dropIfExists('site_page_service_links');
        Schema::table('site_managed_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_revision_id');
        });
        Schema::dropIfExists('site_page_revisions');

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_admin_id');
        });
        Schema::table('site_navigation_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_admin_id');
            $table->dropConstrainedForeignId('created_by_admin_id');
            $table->dropColumn('capability_scope');
        });
        Schema::table('site_media_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by_admin_id');
        });
        Schema::table('site_managed_pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by_admin_id');
            $table->dropConstrainedForeignId('created_by_admin_id');
            $table->dropUnique('mt32_site_pages_public');
            $table->dropColumn(['protected_slug', 'structured_content', 'capability_scope', 'version', 'public_id']);
        });
        Schema::table('site_configuration_revisions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('published_by_admin_id');
            $table->dropConstrainedForeignId('created_by_admin_id');
        });
    }
};
