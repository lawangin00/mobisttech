<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'shops.enter' => 'Enter assigned outlets', 'shop.sales' => 'Create sales / POS invoices',
        'shop.inventory' => 'Manage inventory and stock', 'shop.invoices' => 'View invoices and customer sale history',
        'shop.warranty' => 'Create warranty jobs', 'shop.claims' => 'Manage warranty claims',
        'shop.profile' => 'Edit assigned outlet profile', 'reports.view' => 'View reports and business figures',
        'backups.manage' => 'Create, download and delete backups', 'config.branding.manage' => 'Manage POS branding',
        'config.theme.manage' => 'Manage POS theme', 'config.documents.manage' => 'Manage invoice and warranty templates',
        'config.master-data.manage' => 'Manage product master data', 'config.portal-presentation.manage' => 'Manage portal navigation and presentation',
        'config.dashboard-reports.manage' => 'Manage dashboard and report presentation', 'config.integrations-backups.manage' => 'Manage integrations and backups',
        'config.users-roles.manage' => 'Manage users and roles', 'config.publish' => 'Publish POS configuration',
        'admin.integrations.manage' => 'Connect and manage approved integrations', 'admin.business-profile.manage' => 'Manage the canonical business profile',
        'website.admin-users.manage' => 'Manage Admin accounts and permissions', 'website.audit.view' => 'View Website administration audit',
        'website.content.manage' => 'Manage Website content', 'website.orders.manage' => 'Manage Website orders',
        'website.services.manage' => 'Manage Website services', 'website.service-requests.manage' => 'Manage Website service requests',
        'website.project-quotes.manage' => 'Manage Website project quotes', 'website.settings.manage' => 'Manage Website settings',
        'website.navigation.manage' => 'Manage Website navigation', 'website.branding.manage' => 'Manage Website branding',
        'website.theme.manage' => 'Manage Website theme', 'website.payments.manage' => 'Manage Website payments',
        'website.payment-credentials.manage' => 'Manage Website payment credentials', 'website.integrations.manage' => 'Manage Website integrations',
        'website.seo.manage' => 'Manage Website SEO', 'website.media.manage' => 'Manage Website media',
        'website.publish' => 'Publish Website changes', 'website.mode.preview' => 'Preview Website operating mode',
        'website.mode.publish' => 'Publish Website operating mode', 'website.digital-content.manage' => 'Manage digital content',
        'website.digital-leads.manage' => 'Manage digital leads', 'website.digital-projects.manage' => 'Manage digital projects',
        'website.proposals.approve' => 'Approve proposals', 'website.client-files.manage' => 'Manage client files',
        'website.consultations.manage' => 'Manage consultations', 'website.engagement.manage' => 'Manage engagement',
        'website.conversions.view' => 'View conversion analytics', 'system.reset.transactional' => 'Execute transactional reset',
        'system.reset.business' => 'Execute business reset', 'system.reset.factory' => 'Execute factory reset',
        'shop.stocktake' => 'Count stock', 'shop.stocktake.approve' => 'Approve stock variances',
        'shop.transfers.dispatch' => 'Dispatch stock transfers', 'shop.transfers.receive' => 'Receive stock transfers',
        'shop.procurement' => 'Manage supplier procurement', 'shop.cash' => 'Operate cash sessions',
        'shop.cash.approve' => 'Approve cash variances and expenses', 'shop.trade-in' => 'Manage trade-in intake',
        'shop.labels' => 'Print retail labels', 'shop.bulk-data' => 'Preview and execute approved bulk operations',
        'shop.repairs' => 'Manage paid repair jobs', 'config.promotions.manage' => 'Manage promotions',
        'config.loyalty.manage' => 'Manage loyalty configuration', 'system.reset.preview' => 'Preview a reset',
        'team-members.view' => 'View Team Members and role assignments', 'team-members.manage' => 'Create and manage subordinate Team Members',
        'team-members.roles.manage' => 'Create and manage delegated custom roles', 'team-members.outlets.assign' => 'Assign Team Members to delegated outlets',
        'team-members.full-access.assign' => 'Assign protected Full Access authority', 'team-members.security.manage' => 'Manage Team Member security state',
    ];

    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('job_title', 120)->nullable()->after('profile_photo');
        });
        Schema::table('account_sessions', function (Blueprint $table) {
            $table->dateTime('last_human_activity', 6)->nullable()->after('last_activity');
            $table->index(['guard', 'account_id', 'last_human_activity'], 'mt219_session_human_activity');
        });
        Schema::table('identity_audit_events', function (Blueprint $table) {
            $table->unsignedBigInteger('outlet_id')->nullable()->after('account_id');
            $table->string('actor_name_snapshot', 255)->nullable()->after('reference');
            $table->text('actor_role_snapshot')->nullable()->after('actor_name_snapshot');
            $table->foreign('outlet_id', 'mt219_fk_identity_audit_outlet')->references('id')->on('outlets')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::create('permission_definitions', function (Blueprint $table) {
            $table->string('code', 120)->collation('utf8mb4_bin')->primary();
            $table->string('label', 255);
            $table->dateTime('introduced_at', 6);
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->collation('utf8mb4_bin')->unique();
            $table->string('name', 120)->unique();
            $table->string('slug', 120)->collation('utf8mb4_bin')->unique();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_protected')->default(false);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->dateTime('archived_at', 6)->nullable();
            $table->timestamps(6);
            $table->foreign('created_by_admin_id', 'mt219_fk_role_creator')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('permission_code', 120)->collation('utf8mb4_bin');
            $table->primary(['role_id', 'permission_code']);
            $table->foreign('role_id', 'mt219_fk_role_permission_role')->references('id')->on('roles')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('permission_code', 'mt219_fk_role_permission_code')->references('code')->on('permission_definitions')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('assigned_by_admin_id')->nullable();
            $table->dateTime('assigned_at', 6);
            $table->primary(['admin_id', 'role_id']);
            $table->foreign('admin_id', 'mt219_fk_admin_role_admin')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('role_id', 'mt219_fk_admin_role_role')->references('id')->on('roles')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('assigned_by_admin_id', 'mt219_fk_admin_role_actor')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
        });
        Schema::create('team_member_audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_admin_id');
            $table->unsignedBigInteger('subject_admin_id')->nullable();
            $table->unsignedBigInteger('outlet_id')->nullable();
            $table->string('action', 80)->collation('utf8mb4_bin');
            $table->string('actor_name', 255);
            $table->text('actor_role_snapshot');
            $table->string('subject_name', 255)->nullable();
            $table->text('subject_role_snapshot')->nullable();
            $table->string('outlet_name_snapshot', 255)->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->foreign('actor_admin_id', 'mt219_fk_team_event_actor')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('subject_admin_id', 'mt219_fk_team_event_subject')->references('id')->on('admins')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('outlet_id', 'mt219_fk_team_event_outlet')->references('id')->on('outlets')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['subject_admin_id', 'occurred_at'], 'mt219_team_event_subject_history');
            $table->index(['actor_admin_id', 'occurred_at'], 'mt219_team_event_actor_history');
        });

        $now = now();
        DB::table('permission_definitions')->insert(collect(self::PERMISSIONS)->map(fn ($label, $code) => [
            'code' => $code, 'label' => $label, 'introduced_at' => $now,
        ])->values()->all());

        $rolePermissions = $this->rolePermissions();
        foreach ($rolePermissions as $name => $permissions) {
            $roleId = DB::table('roles')->insertGetId([
                'public_id' => (string) Str::uuid(), 'name' => $name, 'slug' => Str::slug($name),
                'is_system' => true, 'is_protected' => $name === 'Full Access', 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($permissions) {
                DB::table('role_permissions')->insert(array_map(fn ($permission) => ['role_id' => $roleId, 'permission_code' => $permission], $permissions));
            }
        }
    }

    public function down(): void
    {
        abort_if(DB::table('admin_roles')->exists() || DB::table('team_member_audit_events')->exists(), 409,
            'Team Member role or audit evidence must be preserved before rollback.');
        Schema::dropIfExists('team_member_audit_events');
        Schema::dropIfExists('admin_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permission_definitions');
        Schema::table('identity_audit_events', function (Blueprint $table) {
            $table->dropForeign('mt219_fk_identity_audit_outlet');
            $table->dropColumn(['outlet_id', 'actor_name_snapshot', 'actor_role_snapshot']);
        });
        Schema::table('account_sessions', function (Blueprint $table) {
            $table->dropIndex('mt219_session_human_activity');
            $table->dropColumn('last_human_activity');
        });
        Schema::table('admins', fn (Blueprint $table) => $table->dropColumn('job_title'));
    }

    private function rolePermissions(): array
    {
        $all = array_keys(self::PERMISSIONS);

        return [
            'Full Access' => $all,
            'Manager' => array_values(array_diff($all, ['team-members.full-access.assign', 'system.reset.factory'])),
            'Store Manager' => ['shops.enter', 'shop.sales', 'shop.inventory', 'shop.invoices', 'shop.warranty', 'shop.claims', 'shop.profile', 'reports.view', 'shop.procurement', 'shop.stocktake', 'shop.stocktake.approve', 'shop.transfers.dispatch', 'shop.transfers.receive', 'shop.cash', 'shop.cash.approve', 'team-members.view', 'team-members.manage', 'team-members.outlets.assign'],
            'Sales Associate' => ['shops.enter', 'shop.sales', 'shop.invoices', 'shop.warranty'],
            'Cashier' => ['shops.enter', 'shop.sales', 'shop.invoices', 'shop.cash'],
            'Inventory Manager' => ['shops.enter', 'shop.inventory', 'config.master-data.manage', 'shop.procurement', 'shop.stocktake', 'shop.stocktake.approve', 'shop.transfers.dispatch', 'shop.transfers.receive', 'shop.trade-in', 'shop.bulk-data', 'shop.labels', 'reports.view'],
            'Service & Warranty' => ['shops.enter', 'shop.warranty', 'shop.claims', 'shop.repairs'],
            'Online Store Editor' => ['website.content.manage', 'website.navigation.manage', 'website.media.manage', 'website.seo.manage', 'website.mode.preview'],
            'Merchandiser' => ['website.content.manage', 'website.media.manage', 'website.seo.manage', 'config.master-data.manage', 'config.promotions.manage', 'shop.bulk-data'],
            'Customer Support' => ['website.orders.manage', 'website.service-requests.manage', 'shop.invoices'],
            'Digital Operations' => ['website.services.manage', 'website.service-requests.manage', 'website.project-quotes.manage', 'website.digital-content.manage', 'website.digital-leads.manage', 'website.digital-projects.manage', 'website.client-files.manage', 'website.consultations.manage'],
            'Custom Role' => [],
        ];
    }
};
