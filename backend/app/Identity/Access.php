<?php

namespace App\Identity;

use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\Outlet;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Access
{
    public function allows(IdentityAccount $actor, string $permission, ?Outlet $outlet = null): bool
    {
        if (! $actor->usable()) {
            return false;
        }
        if ($actor instanceof User) {
            return $actor->canAdmin($permission);
        }
        if (! array_key_exists($permission, Admin::PERMISSIONS)) {
            return false;
        }
        if (str_starts_with($permission, 'system.reset.')) {
            return $actor instanceof SuperAdmin && $outlet === null;
        }
        if ($actor instanceof SuperAdmin) {
            return $outlet === null || (! $outlet->status && $outlet->archived_at === null);
        }
        if (! $actor instanceof Admin || ! $actor->hasPermission($permission)) {
            return false;
        }
        if ($outlet || str_starts_with($permission, 'shop.') || $permission === 'shops.enter') {
            return $outlet && ! $outlet->status && $outlet->archived_at === null && $actor->hasPermission('shops.enter')
                && $actor->shops()->whereKey($outlet->id)->exists();
        }

        return true;
    }

    public function ownOrder(CustomerAccount $actor, string $publicId): object
    {
        abort_unless($actor->usable() && ! $actor->is_admin, 404);

        return DB::table('orders')->where('public_id', $publicId)->where('user_id', $actor->id)->first()
            ?? abort(404);
    }
}
