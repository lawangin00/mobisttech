<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The POS browser password-change scenario deliberately rejects the old password.
 * IdentityAudit correctly records that failed login without an account ID. Since
 * it cannot be linked by account_id during the usual fixture cleanup, identify it
 * by its one synthetic password-change/login window in the disposable CI schema.
 */
class PosShellAnonymousLoginE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $database = DB::selectOne('SELECT DATABASE() AS name, @@port AS port');
        abort_unless(getenv('CI') === 'true' && app()->environment('testing')
            && $database->name === 'mobisttech_test' && (int) $database->port === 13306, 403);

        DB::transaction(function () {
            $failed = DB::table('identity_audit_events')
                ->where('realm', 'admin')->where('action', 'login_failed')
                ->whereNull('account_id')->whereNull('outlet_id')->whereNull('reference')
                ->whereNull('actor_name_snapshot')->whereNull('actor_role_snapshot');
            $count = $failed->count();
            abort_unless($count <= 1, 409, 'Unexpected anonymous admin login failures in browser fixture.');
            if ($count === 0) {
                return;
            }

            $sales = DB::table('admins')->where('email', 'e2e-sales@example.invalid')
                ->where('name', 'E2E Salesperson')->first(['id']);
            abort_unless($sales !== null, 409, 'Synthetic POS test account is missing.');
            $changed = DB::table('identity_audit_events')->where('realm', 'admin')
                ->where('account_id', $sales->id)->where('action', 'password_replaced')
                ->orderByDesc('created_at')->first(['created_at']);
            abort_unless($changed !== null, 409, 'Synthetic POS password-change evidence is missing.');
            $succeeded = DB::table('identity_audit_events')->where('realm', 'admin')
                ->where('account_id', $sales->id)->where('action', 'login_succeeded')
                ->where('created_at', '>=', $changed->created_at)
                ->orderByDesc('created_at')->first(['created_at']);
            abort_unless($succeeded !== null, 409, 'Synthetic POS follow-up login evidence is missing.');
            abort_unless((clone $failed)->whereBetween('created_at', [$changed->created_at, $succeeded->created_at])->count() === 1,
                409, 'Anonymous login failure does not match the synthetic POS browser scenario.');
            $failed->whereBetween('created_at', [$changed->created_at, $succeeded->created_at])->delete();
        });
    }
}
