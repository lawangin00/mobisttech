<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_protected' => 'boolean', 'archived_at' => 'datetime'];
    }

    public function admins()
    {
        return $this->belongsToMany(Admin::class, 'admin_roles')->withPivot(['assigned_by_admin_id', 'assigned_at']);
    }

    public function permissionCodes(): array
    {
        return $this->newQuery()->whereKey($this->id)->join('role_permissions', 'roles.id', '=', 'role_permissions.role_id')
            ->pluck('role_permissions.permission_code')->all();
    }
}
