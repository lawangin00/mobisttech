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
        $permissions = $this->permissions;

        // Existing administrator accounts remain fully capable until the
        // permissions migration/settings have been applied explicitly.
        if ($permissions === null) {
            return in_array($permission, self::defaultPermissions(), true);
        }

        return in_array($permission, $permissions, true);
    }

    public function shops()
    {
        return $this->belongsToMany(Outlet::class, 'outlet_admins', 'admin_id', 'outlet_id');
    }
}
