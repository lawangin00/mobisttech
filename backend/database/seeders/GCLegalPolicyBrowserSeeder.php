<?php

namespace Database\Seeders;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GCLegalPolicyBrowserSeeder extends Seeder
{
    private const TYPES = [
        'privacy', 'terms', 'returns_refunds', 'shipping_delivery', 'warranty',
        'digital_services_terms', 'payment_disclosures',
    ];

    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_GC_POLICY_E2E') === '1', 403);
        $action = getenv('MT75_GC_POLICY_ACTION');
        abort_unless(in_array($action, ['seed', 'cleanup'], true), 403);

        if ($action === 'seed') {
            abort_unless(DB::table('cms_policies')->whereIn('policy_type', self::TYPES)->doesntExist(), 409,
                'G-C policy fixtures require an empty target policy set.');
            $actor = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
            $request = Request::create('/internal/admin/platform/policies/publish', 'POST');
            $session = app('session')->driver();
            $session->start();
            $request->setLaravelSession($session);
            $request->session()->put('identity_recent_auth_at', now()->timestamp);
            $cms = app(WebsiteCms::class);
            foreach (self::TYPES as $type) {
                $input = [
                    'content' => '<p>G-C synthetic '.$type.' policy content.</p>',
                    'effective_date' => '2026-09-25',
                    'approval_state' => 'owner_approved',
                    'factual_review_state' => 'verified',
                    'unresolved_decisions' => [],
                    'review_notes' => 'Synthetic browser acceptance only; not production legal approval.',
                ];
                if (in_array($type, ['digital_services_terms', 'payment_disclosures'], true)) {
                    $input['applicability_state'] = 'applicable';
                }
                $draft = $cms->savePolicyDraft($actor, $type, $input);
                $cms->publishPolicy($actor, $draft['id'], $request);
            }
            $this->command?->info('G-C exact-owned synthetic policy fixtures published.');

            return;
        }

        $policies = DB::table('cms_policies')->whereIn('policy_type', self::TYPES)->get(['id', 'policy_type']);
        if ($policies->isEmpty()) {
            $this->command?->info('G-C policy fixtures already absent.');

            return;
        }
        abort_unless($policies->count() === count(self::TYPES)
            && $policies->pluck('policy_type')->sort()->values()->all() === collect(self::TYPES)->sort()->values()->all(), 409,
            'G-C policy fixture ownership cannot be established.');
        $ids = $policies->pluck('id')->all();
        $revisions = DB::table('cms_policy_revisions')->whereIn('cms_policy_id', $ids)->pluck('id')->all();
        DB::transaction(function () use ($ids, $revisions) {
            DB::table('cms_policies')->whereIn('id', $ids)->update(['current_revision_id' => null]);
            DB::table('cms_policy_revisions')->whereIn('id', $revisions)->update(['restored_from_revision_id' => null]);
            DB::table('cms_policy_revisions')->whereIn('id', $revisions)->delete();
            DB::table('cms_policies')->whereIn('id', $ids)->delete();
            DB::table('identity_audit_events')->where('realm', 'admin')
                ->whereIn('reference', array_map(fn ($id) => 'cms_policy_revision:'.$id, $revisions))
                ->whereIn('action', ['website_policy_draft_saved', 'website_policy_published', 'website_policy_rolled_back'])->delete();
        });
        $this->command?->info('G-C exact-owned synthetic policy fixtures cleaned.');
    }
}
