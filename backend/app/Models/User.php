<?php

namespace App\Models;

use App\Identity\IdentityAccount;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'mobile', 'profile_photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends IdentityAccount
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ADMIN_ROLES = [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'content_editor' => 'Content Editor',
        'operations' => 'Sales & Service Executive',
    ];

    public const CONFIGURATION_PERMISSIONS = [
        'website.content.manage' => 'Manage Website Content',
        'website.navigation.manage' => 'Manage Website Navigation',
        'website.branding.manage' => 'Manage Website Branding',
        'website.theme.manage' => 'Manage Website Theme',
        'website.payments.manage' => 'Manage Website Payments',
        'website.payment-credentials.manage' => 'Manage Payment Credentials',
        'website.integrations.manage' => 'Manage Website/POS Integrations',
        'website.seo.manage' => 'Manage Website SEO',
        'website.media.manage' => 'Manage Website Media',
        'website.publish' => 'Publish Website Changes',
    ];

    private const ROLE_PERMISSIONS = [
        'owner' => ['admin-users', 'audit-log', 'content', 'orders', 'services', 'service-requests', 'project-quotes', 'settings', 'website.content.manage', 'website.navigation.manage', 'website.branding.manage', 'website.theme.manage', 'website.payments.manage', 'website.payment-credentials.manage', 'website.integrations.manage', 'website.seo.manage', 'website.media.manage', 'website.publish'],
        'manager' => ['audit-log', 'content', 'orders', 'services', 'service-requests', 'project-quotes', 'settings', 'website.content.manage', 'website.navigation.manage', 'website.branding.manage', 'website.theme.manage', 'website.payments.manage', 'website.integrations.manage', 'website.seo.manage', 'website.media.manage', 'website.publish'],
        'content_editor' => ['content', 'services', 'settings', 'website.content.manage', 'website.seo.manage', 'website.media.manage', 'website.publish'],
        'operations' => ['orders', 'service-requests', 'project-quotes'],
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'auth_version' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    public function adminRole(): ?string
    {
        if (! $this->is_admin) {
            return null;
        }

        return $this->admin_role; // Legacy owner fallback requires explicit audited import.
    }

    public function canAdmin(string $ability): bool
    {
        $role = $this->adminRole();
        if (! $role) {
            return false;
        }

        return in_array($ability, self::ROLE_PERMISSIONS[$role] ?? [], true);
    }
}
