<?php

namespace Database\Seeders;

use App\Identity\CustomerIdentity;
use App\Models\CustomerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class W05ForeignCustomerE2eSeeder extends Seeder
{
    private const EMAIL = 'mt55-foreign-customer@example.invalid';

    private const PUBLIC_ID = '00000000-0000-4000-8000-000000005599';

    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        $mode = getenv('MT75_W05_FOREIGN_FIXTURE_ACTION');
        abort_unless(in_array($mode, ['create', 'cleanup'], true), 403);
        DB::transaction(function () use ($mode): void {
            $user = DB::table('users')->where('email', self::EMAIL)->first();
            $collision = DB::table('users')->where('public_id', self::PUBLIC_ID)->first();
            abort_unless(! $collision || ($user && $user->id === $collision->id), 409);
            if ($mode === 'create') {
                abort_unless(! $user && ! $collision && ! DB::table('users')->where('mobile', '03005559999')->exists(), 409);
                $created = new CustomerAccount;
                $created->forceFill(['public_id' => self::PUBLIC_ID, 'name' => 'MT55 Foreign Customer',
                    'email' => self::EMAIL, 'mobile' => '03005559999', 'password' => Hash::make('SyntheticPass123!'),
                    'is_admin' => false, 'auth_version' => 1, 'email_verified_at' => now()])->save();
                app(CustomerIdentity::class)->forAccount($created);

                return;
            }
            if (! $user) {
                return;
            }
            abort_unless($user->public_id === self::PUBLIC_ID && $user->name === 'MT55 Foreign Customer'
                && $user->mobile === '03005559999' && ! $user->is_admin, 409);
            $customer = DB::table('customers')->where('website_user_id', $user->id)->first();
            abort_unless($customer && $customer->display_name === $user->name
                && ! DB::table('client_projects')->where('customer_account_id', $user->id)->exists()
                && ! DB::table('orders')->where('user_id', $user->id)->exists()
                && ! DB::table('project_files')->where('uploaded_by_customer_account_id', $user->id)->exists(), 409);
            // Visiting the real account UI initializes its default preferences.
            // Only the exact fixture's untouched canonical default is disposable.
            $preference = DB::table('notification_preferences')->where('customer_account_id', $user->id)->first();
            abort_unless(! $preference || ((bool) $preference->email_enabled
                && (int) $preference->max_per_hour === 5 && (int) $preference->version === 1),
                409, 'Foreign Customer preferences changed; refuse fixture cleanup.');
            abort_unless(! DB::table('product_notification_subscriptions')->where('customer_account_id', $user->id)->exists()
                && ! DB::table('wishlist_items')->where('customer_account_id', $user->id)->exists()
                && ! DB::table('notification_rate_buckets')->where('customer_account_id', $user->id)->exists(),
                409, 'Foreign Customer has non-fixture engagement data; refuse cleanup.');
            DB::table('notification_preferences')->where('customer_account_id', $user->id)->delete();
            DB::table('account_sessions')->where('guard', 'customer')->where('account_id', $user->id)->delete();
            DB::table('identity_audit_events')->where('realm', 'customer')->where('account_id', $user->id)->delete();
            DB::table('customers')->where('id', $customer->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        });
    }
}
