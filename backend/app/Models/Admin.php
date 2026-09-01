<?php

namespace App\Models;

use App\Addendum\Permissions;
use App\Identity\IdentityAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Admin extends IdentityAccount
{
    use HasFactory, Notifiable;

    public const OPERATIONAL_PERMISSIONS = [
        'shops.enter' => 'Enter assigned outlets',
        'shop.sales' => 'Create sales / POS invoices',
        'shop.inventory' => 'Manage inventory and stock',
        'shop.invoices' => 'View invoices and customer sale history',
        'shop.warranty' => 'Create warranty jobs',
        'shop.claims' => 'Manage warranty claims',
        'shop.profile' => 'Edit assigned outlet profile',
        'reports.view' => 'View reports and business figures',
        'backups.manage' => 'Create, download and delete backups',
    ];

    public const CONFIGURATION_PERMISSIONS = [
        'config.branding.manage' => 'Manage POS Branding',
        'config.theme.manage' => 'Manage POS Theme',
        'config.documents.manage' => 'Manage Invoice/Warranty Templates',
        'config.master-data.manage' => 'Manage Product Master Data',
        'config.portal-presentation.manage' => 'Manage Portal Navigation/Presentation',
        'config.dashboard-reports.manage' => 'Manage Dashboard/Report Presentation',
        'config.integrations-backups.manage' => 'Manage Integrations/Backups',
        'config.users-roles.manage' => 'Manage Users/Roles',
        'config.publish' => 'Publish POS Configuration',
        'admin.integrations.manage' => 'Connect and manage approved integrations',
        'admin.business-profile.manage' => 'Manage the canonical business profile',
        'website.admin-users.manage' => 'Manage Admin accounts and permissions',
        'website.audit.view' => 'View Website administration audit',
        'website.content.manage' => 'Manage Website content',
        'website.orders.manage' => 'Manage Website orders',
        'website.services.manage' => 'Manage Website services',
        'website.service-requests.manage' => 'Manage Website service requests',
        'website.project-quotes.manage' => 'Manage Website project quotes',
        'website.settings.manage' => 'Manage Website settings',
        'website.navigation.manage' => 'Manage Website navigation',
        'website.branding.manage' => 'Manage Website branding',
        'website.theme.manage' => 'Manage Website theme',
        'website.payments.manage' => 'Manage Website payments',
        'website.payment-credentials.manage' => 'Manage Website payment credentials',
        'website.integrations.manage' => 'Manage Website integrations',
        'website.seo.manage' => 'Manage Website SEO',
        'website.media.manage' => 'Manage Website media',
        'website.publish' => 'Publish Website changes',
        'website.mode.preview' => 'Preview Website operating mode',
        'website.mode.publish' => 'Publish Website operating mode',
        'website.digital-content.manage' => 'Manage digital content',
        'website.digital-leads.manage' => 'Manage digital leads',
        'website.digital-projects.manage' => 'Manage digital projects',
        'website.proposals.approve' => 'Approve proposals',
        'website.client-files.manage' => 'Manage client files',
        'website.consultations.manage' => 'Manage consultations',
        'website.engagement.manage' => 'Manage engagement',
        'website.conversions.view' => 'View conversion analytics',
        'team-members.view' => 'View Team Members and role assignments',
        'team-members.manage' => 'Create and manage subordinate Team Members',
        'team-members.roles.manage' => 'Create and manage delegated custom roles',
        'team-members.outlets.assign' => 'Assign Team Members to delegated outlets',
        'team-members.full-access.assign' => 'Assign protected Full Access authority',
        'team-members.security.manage' => 'Manage Team Member security state',
    ];

    public const PERMISSIONS = self::OPERATIONAL_PERMISSIONS + self::CONFIGURATION_PERMISSIONS + Permissions::POS;

    protected $guarded = ['*'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'auth_version' => 'integer',
            'archived_at' => 'datetime',
            'permissions' => 'array',
        ];
    }

    public static function defaultPermissions(): array
    {
        return array_keys(self::OPERATIONAL_PERMISSIONS);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    public function effectivePermissions(): array
    {
        $permissions = $this->permissions;

        // Existing administrator accounts remain fully capable until the
        // permissions migration/settings have been applied explicitly.
        if ($permissions === null) {
            $permissions = self::defaultPermissions();
        }

        $rolePermissions = $this->roles()->join('role_permissions', 'roles.id', '=', 'role_permissions.role_id')
            ->whereNull('roles.archived_at')->pluck('role_permissions.permission_code')->all();

        return array_values(array_unique([...$permissions, ...$rolePermissions]));
    }

    public function shops()
    {
        return $this->belongsToMany(Outlet::class, 'outlet_admins', 'admin_id', 'outlet_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'admin_roles')->withPivot(['assigned_by_admin_id', 'assigned_at']);
    }

    public function roleNames(): array
    {
        return $this->roles()->whereNull('roles.archived_at')->orderBy('roles.name')->pluck('roles.name')->all();
    }

    public function roleSnapshot(): string
    {
        $roles = $this->roleNames();

        return $roles ? implode(', ', $roles) : 'Legacy direct permissions';
    }
}
