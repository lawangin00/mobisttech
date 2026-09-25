<?php

namespace Database\Seeders;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GAKnowledgeBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_GA_KNOWLEDGE_E2E') === '1', 403);
        $action = getenv('MT75_GA_KNOWLEDGE_ACTION');
        abort_unless(in_array($action, ['seed', 'cleanup'], true), 403);
        $slugs = ['ga-e2e-knowledge-faq', 'ga-e2e-knowledge-insight', 'ga-e2e-knowledge-guide'];

        if ($action === 'seed') {
            $actor = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
            abort_unless(DB::table('site_managed_pages')->whereIn('slug', $slugs)->doesntExist(), 409);
            $cms = app(WebsiteCms::class);
            $faq = $cms->savePageDraft($actor, null, [
                'title' => 'GA reusable knowledge FAQ', 'slug' => $slugs[0], 'content' => '<p>GA public FAQ introduction.</p>',
                'content_purpose' => 'faq', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'seo_title' => 'GA reusable FAQ SEO',
                'structured_content' => [
                    'category' => 'Website Support', 'tags' => ['Laravel', 'Security'],
                    'items' => [['question' => 'How is this FAQ reused?', 'answer' => '<p>Through typed published FAQ items.</p>']],
                ],
            ]);
            $cms->publishPage($actor, $faq['id']);
            $cms->savePageDraft($actor, $faq['page_public_id'], [
                'title' => 'GA reusable knowledge FAQ', 'slug' => $slugs[0], 'content' => '<p>GA_PRIVATE_KNOWLEDGE_DRAFT</p>',
                'content_purpose' => 'faq', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => [
                    'category' => 'Private Future', 'tags' => ['Private'],
                    'items' => [['question' => 'Private future question?', 'answer' => '<p>Private future answer.</p>']],
                ],
            ]);
            $insight = $cms->savePageDraft($actor, null, [
                'title' => 'GA engineering insight', 'slug' => $slugs[1], 'content' => '<p>GA published engineering insight.</p>',
                'content_purpose' => 'insight', 'capability_scope' => 'digital',
                'structured_content' => ['category' => 'Engineering', 'tags' => ['Architecture']],
            ]);
            $cms->publishPage($actor, $insight['id']);
            $guide = $cms->savePageDraft($actor, null, [
                'title' => 'GA service guide', 'slug' => $slugs[2], 'content' => '<p>GA published service guide.</p>',
                'content_purpose' => 'guide', 'capability_scope' => 'digital',
                'service_slugs' => ['mt54-web-development'],
                'structured_content' => ['category' => 'Getting Started', 'tags' => ['Guide']],
            ]);
            $cms->publishPage($actor, $guide['id']);
            $this->command?->info('GA exact-owned knowledge FAQ, insight, guide and private draft created.');

            return;
        }

        $pages = DB::table('site_managed_pages')->whereIn('slug', $slugs)->get(['id', 'slug']);
        if ($pages->isEmpty()) {
            $this->command?->info('GA knowledge browser fixtures already absent.');

            return;
        }
        abort_unless($pages->count() === 3 && $pages->pluck('slug')->sort()->values()->all() === collect($slugs)->sort()->values()->all(),
            409, 'GA knowledge test-only page ownership not established.');
        $ids = $pages->pluck('id')->all();
        $revisions = DB::table('site_page_revisions')->whereIn('site_managed_page_id', $ids)->pluck('id')->all();
        DB::transaction(function () use ($ids, $revisions) {
            DB::table('site_page_service_links')->whereIn('site_managed_page_id', $ids)->delete();
            DB::table('site_managed_pages')->whereIn('id', $ids)->update(['current_revision_id' => null]);
            DB::table('site_page_revisions')->whereIn('id', $revisions)->update(['restored_from_revision_id' => null]);
            DB::table('site_page_revisions')->whereIn('id', $revisions)->delete();
            DB::table('site_managed_pages')->whereIn('id', $ids)->delete();
            DB::table('identity_audit_events')->where('realm', 'admin')
                ->whereIn('reference', array_map(fn ($id) => 'site_page_revision:'.$id, $revisions))
                ->whereIn('action', ['website_page_draft_saved', 'website_page_published'])->delete();
        });
        $this->command?->info('GA exact-owned knowledge fixtures and audit cleaned.');
    }
}
