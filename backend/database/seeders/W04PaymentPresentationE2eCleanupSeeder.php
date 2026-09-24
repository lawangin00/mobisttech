<?php

namespace Database\Seeders;

use App\Commerce\WebsitePaymentPresentation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Guarded cleanup: only the dedicated synthetic editor and the nonsecret presentation domain. */
final class W04PaymentPresentationE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing')
            && (getenv('CI') === 'true' || getenv('MT75_FIRST_OUTLET_E2E_ENABLED') === '1')
            && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        $admin = DB::table('admins')->where('email', 'e2e-w04-payment-editor@example.invalid')->first();
        abort_unless($admin !== null, 409, 'Synthetic presentation browser editor is missing.');

        DB::transaction(function () use ($admin): void {
            $revisions = DB::table('site_configuration_revisions')
                ->where('domain', 'website.payments.presentation')->lockForUpdate()->get();
            foreach ($revisions as $revision) {
                try {
                    $policy = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
                    app(WebsitePaymentPresentation::class)->validate($policy);
                } catch (\JsonException|HttpException|\TypeError) {
                    throw new RuntimeException('Unexpected presentation revision; refuse synthetic cleanup.');
                }
                if ((int) $revision->created_by_admin_id !== (int) $admin->id
                    || ($revision->published_by_admin_id !== null && (int) $revision->published_by_admin_id !== (int) $admin->id)
                    || ! in_array($revision->state, ['draft', 'published', 'superseded'], true)) {
                    throw new RuntimeException('Unowned presentation revision; refuse synthetic cleanup.');
                }
            }
            if ($revisions->isEmpty()) {
                return;
            }
            DB::table('site_configuration_revisions')->whereIn('id', $revisions->pluck('id')->all())->delete();
            DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $admin->id)
                ->whereIn('action', ['website_payment_presentation_draft_saved', 'website_payment_presentation_published'])
                ->delete();
        });
    }
}
