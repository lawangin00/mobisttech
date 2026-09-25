<?php

namespace Database\Seeders;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GADigitalLandingBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_GA_SERVICE_LANDING_E2E') === '1', 403);
        $action = getenv('MT75_GA_SERVICE_LANDING_ACTION');
        abort_unless(in_array($action, ['seed', 'cleanup'], true), 403);
        $slug = 'ga-e2e-published-service-landing';
        $actor = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
        if ($action === 'seed') {
            abort_unless(DB::table('site_managed_pages')->where('slug', $slug)->doesntExist(), 409);
            $cms = app(WebsiteCms::class);
            $first = $cms->savePageDraft($actor, null, [
                'title' => 'GA service details', 'slug' => $slug, 'content' => '<p>GA published service body.</p>',
                'content_purpose' => 'service_landing', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'], 'seo_title' => 'GA authored service SEO',
                'meta_description' => 'GA managed service description',
                'structured_content' => ['hero_heading' => 'GA rich published service', 'hero_body' => 'GA hero body',
                    'problem' => 'GA identified customer problem', 'outcome' => 'GA approved digital outcome',
                    'features' => ['Secure service feature'], 'deliverables' => ['GA delivery'],
                    'process' => ['GA discovery'], 'technologies' => ['Laravel'],
                    'faq' => [['question' => 'GA service question?', 'answer' => 'GA safe service answer.']],
                    'private_internal_notes' => 'GA_HIDDEN_INTERNAL_VALUE'],
            ]);
            $cms->publishPage($actor, $first['id']);
            $cms->savePageDraft($actor, $first['page_public_id'], [
                'title' => 'GA service details', 'slug' => $slug, 'content' => '<p>GA_PRIVATE_UNPUBLISHED_BODY</p>',
                'content_purpose' => 'service_landing', 'capability_scope' => 'digital', 'service_slugs' => ['mt54-web-development'],
            ]);
            $case = $cms->savePageDraft($actor, null, [
                'title' => 'GA related case study', 'slug' => 'ga-e2e-related-case', 'content' => '<p>GA case details</p>',
                'content_purpose' => 'case_study', 'capability_scope' => 'digital', 'service_slugs' => ['mt54-web-development'],
                'structured_content' => ['client_disclosure' => 'anonymous', 'category' => 'Web Development',
                    'industry' => 'GA_HIDDEN_CASE_INDUSTRY', 'problem' => 'GA case challenge', 'solution' => 'GA case solution',
                    'technologies' => ['Laravel'], 'outcomes' => ['GA measured outcome']],
            ]);
            $cms->publishPage($actor, $case['id']);
            $this->command?->info('GA exact-owned published service landing, private draft and case created.');

            return;
        }
        $pages = DB::table('site_managed_pages')->whereIn('slug', [$slug, 'ga-e2e-related-case'])
            ->where('created_by_admin_id', $actor->id)->get(['id', 'public_id', 'slug']);
        abort_unless($pages->count() === 2 && $pages->pluck('slug')->sort()->values()->all() === [
            'ga-e2e-published-service-landing', 'ga-e2e-related-case',
        ], 409, 'GA test-only page ownership not established.');
        $ids = $pages->pluck('id')->all();
        $revisions = DB::table('site_page_revisions')->whereIn('site_managed_page_id', $ids)->pluck('id')->all();
        DB::transaction(function () use ($ids, $revisions, $actor) {
            DB::table('site_page_service_links')->whereIn('site_managed_page_id', $ids)->delete();
            DB::table('site_managed_pages')->whereIn('id', $ids)->update(['current_revision_id' => null]);
            DB::table('site_page_revisions')->whereIn('id', $revisions)->update(['restored_from_revision_id' => null]);
            DB::table('site_page_revisions')->whereIn('id', $revisions)->delete();
            DB::table('site_managed_pages')->whereIn('id', $ids)->delete();
            DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $actor->id)
                ->whereIn('reference', array_map(fn ($id) => 'site_page_revision:'.$id, $revisions))
                ->whereIn('action', ['website_page_draft_saved', 'website_page_published'])->delete();
        });
        $this->command?->info('GA exact-owned published service pages/drafts/associations/audit cleaned.');
    }
}
