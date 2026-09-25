<?php

namespace Database\Seeders;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GATestimonialBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_GA_TESTIMONIAL_E2E') === '1', 403);
        $action = getenv('MT75_GA_TESTIMONIAL_ACTION');
        abort_unless(in_array($action, ['seed', 'cleanup'], true), 403);
        $actor = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
        $slugs = ['ga-e2e-testimonial-case', 'ga-e2e-testimonial-first', 'ga-e2e-testimonial-pending'];

        if ($action === 'seed') {
            abort_unless(DB::table('site_managed_pages')->whereIn('slug', $slugs)->doesntExist(), 409);
            $cms = app(WebsiteCms::class);
            $case = $cms->savePageDraft($actor, null, [
                'title' => 'GA testimonial case study', 'slug' => $slugs[0], 'content' => '<p>GA testimonial case body.</p>',
                'content_purpose' => 'case_study', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => ['client_disclosure' => 'anonymous'],
            ]);
            $cms->publishPage($actor, $case['id']);
            $first = $cms->savePageDraft($actor, null, [
                'title' => 'GA first approved testimonial', 'slug' => $slugs[1], 'content' => '<p>GA first approved feedback.</p>',
                'content_purpose' => 'digital_testimonial', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => [
                    'consent_confirmed' => true, 'moderation_state' => 'approved',
                    'display_enabled' => true, 'display_order' => 10,
                    'case_study_slugs' => [$slugs[0]],
                ],
            ]);
            $cms->publishPage($actor, $first['id']);
            $cms->savePageDraft($actor, null, [
                'title' => 'GA pending testimonial', 'slug' => $slugs[2], 'content' => '<p>GA pending feedback body.</p>',
                'content_purpose' => 'digital_testimonial', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => [
                    'consent_confirmed' => true, 'moderation_state' => 'pending',
                    'display_enabled' => false, 'display_order' => 100,
                    'case_study_slugs' => [$slugs[0]],
                ],
            ]);
            $this->command?->info('GA exact-owned testimonial case, approved testimonial and pending testimonial created.');

            return;
        }

        $pages = DB::table('site_managed_pages')->whereIn('slug', $slugs)
            ->where('created_by_admin_id', $actor->id)->get(['id', 'slug']);
        abort_unless($pages->count() === 3 && $pages->pluck('slug')->sort()->values()->all() === collect($slugs)->sort()->values()->all(),
            409, 'GA testimonial test-only page ownership not established.');
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
                ->whereIn('action', ['website_page_draft_saved', 'website_page_published', 'website_page_rolled_back'])->delete();
        });
        $this->command?->info('GA exact-owned testimonial fixtures and audit cleaned.');
    }
}
