<?php

namespace Database\Seeders;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GACaseStudyBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_GA_CASE_E2E') === '1', 403);
        $action = getenv('MT75_GA_CASE_ACTION');
        abort_unless(in_array($action, ['seed', 'cleanup'], true), 403);
        $slugs = ['ga-e2e-case-hidden', 'ga-e2e-case-earlier', 'ga-e2e-case-visible', 'ga-e2e-case-feedback'];

        if ($action === 'seed') {
            $actor = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
            abort_unless(DB::table('site_managed_pages')->whereIn('slug', $slugs)->doesntExist(), 409);
            abort_unless(DB::table('site_media_assets')->where('original_name', 'ga-e2e-case.png')->doesntExist(), 409);
            $cms = app(WebsiteCms::class);
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=');
            $media = $cms->registerMedia($actor, [
                'bytes' => $png, 'extension' => 'png', 'original_name' => 'ga-e2e-case.png', 'alt_text' => 'GA case screenshot',
            ]);
            $hidden = $cms->savePageDraft($actor, null, [
                'title' => 'GA hidden case', 'slug' => $slugs[0], 'content' => '<p>GA hidden case.</p>',
                'content_purpose' => 'case_study', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => ['client_disclosure' => 'anonymous', 'display_enabled' => false, 'display_order' => 1],
            ]);
            $cms->publishPage($actor, $hidden['id']);
            $earlier = $cms->savePageDraft($actor, null, [
                'title' => 'GA earlier case', 'slug' => $slugs[1], 'content' => '<p>GA earlier case body.</p>',
                'content_purpose' => 'case_study', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => ['client_disclosure' => 'industry_only', 'industry' => 'Technology', 'display_enabled' => true, 'display_order' => 10],
            ]);
            $cms->publishPage($actor, $earlier['id']);
            $visible = $cms->savePageDraft($actor, null, [
                'title' => 'GA visible case', 'slug' => $slugs[2], 'content' => '<p>GA visible case body.</p>',
                'content_purpose' => 'case_study', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => [
                    'client_disclosure' => 'anonymous', 'industry' => 'GA_PRIVATE_INDUSTRY',
                    'category' => 'Web Development', 'problem' => 'GA case challenge', 'solution' => 'GA case solution',
                    'technologies' => ['Laravel'], 'outcomes' => ['GA measured case outcome'],
                    'screenshot_media_ids' => [$media['id']], 'display_enabled' => true, 'display_order' => 20,
                ],
            ]);
            $cms->publishPage($actor, $visible['id']);
            $feedback = $cms->savePageDraft($actor, null, [
                'title' => 'GA case-linked feedback', 'slug' => $slugs[3], 'content' => '<p>GA linked case feedback.</p>',
                'content_purpose' => 'digital_testimonial', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => [
                    'consent_confirmed' => true, 'moderation_state' => 'approved', 'display_enabled' => true,
                    'display_order' => 30, 'case_study_slugs' => [$slugs[2]],
                ],
            ]);
            $cms->publishPage($actor, $feedback['id']);
            $this->command?->info('GA exact-owned case visibility/order/media/testimonial fixtures created.');

            return;
        }

        $pages = DB::table('site_managed_pages')->whereIn('slug', $slugs)->get(['id', 'slug']);
        $media = DB::table('site_media_assets')->where('original_name', 'ga-e2e-case.png')->first();
        if ($pages->isEmpty() && ! $media) {
            $this->command?->info('GA case browser fixtures already absent.');

            return;
        }
        abort_unless($pages->count() === 4 && $pages->pluck('slug')->sort()->values()->all() === collect($slugs)->sort()->values()->all(),
            409, 'GA case test-only page ownership not established.');
        abort_unless($media, 409, 'GA case test-only media ownership not established.');
        $ids = $pages->pluck('id')->all();
        $revisions = DB::table('site_page_revisions')->whereIn('site_managed_page_id', $ids)->pluck('id')->all();
        DB::transaction(function () use ($ids, $revisions, $media) {
            DB::table('site_page_service_links')->whereIn('site_managed_page_id', $ids)->delete();
            DB::table('site_managed_pages')->whereIn('id', $ids)->update(['current_revision_id' => null]);
            DB::table('site_page_revisions')->whereIn('id', $revisions)->update(['restored_from_revision_id' => null]);
            DB::table('site_page_revisions')->whereIn('id', $revisions)->delete();
            DB::table('site_managed_pages')->whereIn('id', $ids)->delete();
            DB::table('identity_audit_events')->where('realm', 'admin')
                ->whereIn('reference', array_map(fn ($id) => 'site_page_revision:'.$id, $revisions))
                ->whereIn('action', ['website_page_draft_saved', 'website_page_published'])->delete();
            DB::table('identity_audit_events')->where('realm', 'admin')
                ->where('reference', 'site_media_asset:'.$media->id)->where('action', 'website_media_registered')->delete();
            DB::table('site_media_assets')->where('id', $media->id)->delete();
        });
        Storage::disk('local')->delete($media->path);
        $this->command?->info('GA exact-owned case fixtures/media/audit cleaned.');
    }
}
