<?php

namespace App\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

final class CustomerIdentity
{
    public function forAccount(User $account): object
    {
        return DB::transaction(function () use ($account) {
            $user = DB::table('users')->where('id', $account->id)->lockForUpdate()->first();
            if (! $user || $user->is_admin || $user->archived_at !== null) {
                throw new LogicException('Only an active customer account can own a customer identity.');
            }
            $customer = DB::table('customers')->where('website_user_id', $user->id)->first();
            if ($customer) {
                return $customer;
            }
            $id = DB::table('customers')->insertGetId(['public_id' => (string) Str::uuid(), 'website_user_id' => $user->id,
                'display_name' => $user->name, 'email' => $user->email, 'mobile' => $user->mobile, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('customers')->where('id', $id)->first();
        });
    }
}
