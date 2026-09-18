<?php

namespace Database\Seeders;

use App\Cms\WebsiteCms;
use App\Digital\DigitalServiceLeads;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DynamicWebsiteE2eSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDatabaseName() !== 'mobisttech_test') {
            throw new \RuntimeException('Dynamic Website E2E seeding is restricted to mobisttech_test.');
        }

        app(DynamicWebsiteE2eCleanupSeeder::class)->run();

        $actor = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
        $digitalActor = Admin::where('email', 'e2e-digital-operations@example.invalid')->firstOrFail();
        $digital = app(DigitalServiceLeads::class);
        $service = $digital->configureService($digitalActor, [
            'slug' => 'mt54-web-development', 'name' => 'MT54 Web Development',
            'short_description' => 'Synthetic published digital service', 'description' => 'Published service detail for MT-5.4.',
            'price_type' => 'package', 'price' => null, 'is_active' => true,
            'packages' => [['code' => 'starter', 'name' => 'Starter', 'pricing_type' => 'fixed', 'price' => '10000.00']],
            'addons' => [['code' => 'seo', 'name' => 'SEO Setup', 'pricing_type' => 'fixed', 'price' => '2500.00']],
        ]);
        $digital->configureConsultation($digitalActor, [
            'enabled' => true, 'timezone' => 'Asia/Karachi',
            'weekly_availability' => [['day' => 1, 'start' => '09:00', 'end' => '17:00']],
        ]);

        $cms = app(WebsiteCms::class);
        $home = $cms->savePageDraft($actor, null, [
            'title' => 'MT54 Home', 'slug' => 'home', 'content' => '<p>Managed homepage published content.</p>',
            'content_purpose' => 'homepage', 'capability_scope' => 'common',
            'structured_content' => ['hero_heading' => 'MT54 managed homepage', 'hero_body' => 'Published homepage copy from the CMS authority.'],
        ]);
        $cms->publishPage($actor, $home['id']);

        $case = $cms->savePageDraft($actor, null, [
            'title' => 'MT54 Case Study', 'slug' => 'mt54-case-study', 'content' => '<p>Published anonymous case study.</p>',
            'content_purpose' => 'case_study', 'capability_scope' => 'digital',
            'structured_content' => ['client_disclosure' => 'anonymous'],
        ]);
        $cms->publishPage($actor, $case['id']);
        $cms->savePageDraft($actor, $case['page_public_id'], [
            'title' => 'MT54 Case Study', 'slug' => 'mt54-case-study', 'content' => '<p>Private case-study draft.</p>',
            'content_purpose' => 'case_study', 'capability_scope' => 'digital',
            'structured_content' => ['client_disclosure' => 'anonymous'],
        ]);

        $policy = $cms->savePolicyDraft($actor, 'privacy', [
            'content' => '<p>Published MT54 privacy facts.</p>', 'effective_date' => '2026-09-18',
            'approval_state' => 'owner_approved', 'factual_review_state' => 'verified', 'unresolved_decisions' => [],
        ]);
        $cms->publishPolicy($actor, $policy['id'], $this->recentAuthRequest());

        $software = $cms->saveSoftwareDraft($actor, null, $this->softwareInput('Published MT54 software overview'));
        $cms->publishSoftware($actor, $software['id']);
        $cms->saveSoftwareDraft($actor, $software['software_public_id'], $this->softwareInput('Private MT54 software draft'));
        $release = $cms->saveReleaseDraft($actor, $software['software_public_id'], [
            'version' => '1.0.0', 'release_date' => '2026-09-18', 'summary' => 'MT54 initial public release',
            'notes' => ['added' => ['Reusable public Software Product pages']], 'impact_review' => $this->releaseImpact(),
        ]);
        $releaseId = DB::table('software_releases')->where('public_id', $release['public_id'])->value('id');
        $cms->publishRelease($actor, (int) $releaseId);
    }

    private function recentAuthRequest(): Request
    {
        $request = Request::create('/e2e/policy', 'POST');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->session()->put('identity_recent_auth_at', now()->timestamp);

        return $request;
    }

    private function softwareInput(string $overview): array
    {
        return [
            'name' => 'MT54 Software', 'slug' => 'mt54-software', 'summary' => 'Synthetic reusable software summary',
            'overview' => $overview, 'features' => [['title' => 'Published feature', 'description' => 'Customer-readable feature']],
            'platforms' => ['Windows 10 x64', 'Windows 11 x64'], 'system_requirements' => '<p>Supported Windows environment.</p>',
            'limitations' => ['External providers require separate configuration.'], 'support' => ['channel' => 'support'],
            'cta' => ['type' => 'contact'], 'privacy' => '<p>MT54 product privacy.</p>', 'terms' => '<p>MT54 product terms.</p>',
            'faq' => [['question' => 'Is this published?', 'answer' => '<p>Yes, from the reusable template.</p>']],
            'seo_title' => 'MT54 Software', 'seo_description' => 'Synthetic Software SEO', 'sitemap' => true,
        ];
    }

    private function releaseImpact(): array
    {
        return [
            'overview' => 'reviewed_no_change', 'privacy' => 'reviewed_no_change', 'terms' => 'reviewed_no_change',
            'faq' => 'reviewed_no_change', 'system_requirements' => 'reviewed_no_change',
            'support_guidance' => 'reviewed_no_change', 'material' => [],
        ];
    }
}
