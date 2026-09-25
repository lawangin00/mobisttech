<?php

namespace Tests\Feature;

use App\Cms\WebsiteCms;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class GCLegalPolicyMatrixTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_all_consolidated_policy_types_have_protected_versioned_public_destinations_without_fabricating_cookie_need(): void
    {
        $cms = app(WebsiteCms::class);
        $request = $this->recentAuthRequest();
        $required = [
            'privacy' => ['privacy-policy', 'Privacy Policy'],
            'terms' => ['terms-conditions', 'Terms & Conditions'],
            'returns_refunds' => ['return-refund-policy', 'Return & Refund Policy'],
            'shipping_delivery' => ['shipping-delivery-policy', 'Shipping / Delivery Policy'],
            'warranty' => ['warranty-policy', 'Warranty Policy'],
        ];
        $conditional = [
            'digital_services_terms' => ['digital-services-terms', 'Digital Services / Project Terms'],
            'payment_disclosures' => ['payment-disclosures', 'Payment Disclosures'],
        ];

        foreach ($required + $conditional as $type => [$slug, $title]) {
            $input = [
                'content' => '<p>Synthetic reviewed '.$type.' policy.</p>',
                'effective_date' => '2026-09-25',
                'approval_state' => 'owner_approved',
                'factual_review_state' => 'verified',
                'unresolved_decisions' => [],
                'professional_review_reference' => 'synthetic-gc-review',
                'review_notes' => 'Synthetic acceptance fixture only; not production legal approval.',
            ];
            if (isset($conditional[$type])) {
                $input['applicability_state'] = 'applicable';
            }
            $draft = $cms->savePolicyDraft($this->actor, $type, $input);
            $cms->publishPolicy($this->actor, $draft['id'], $request);
            $row = DB::table('cms_policies')->where('policy_type', $type)->firstOrFail();
            $this->assertSame($slug, $row->slug);
            $this->assertSame($title, $row->title);
            $this->assertTrue((bool) $row->protected_slug);
            $this->assertNotNull($row->footer_destination);
        }

        $cookie = $cms->savePolicyDraft($this->actor, 'cookie', [
            'content' => '',
            'effective_date' => '2026-09-25',
            'approval_state' => 'owner_approved',
            'factual_review_state' => 'verified',
            'applicability_state' => 'not_required',
            'unresolved_decisions' => [],
            'review_notes' => 'No non-essential tracking in the current technical fact audit.',
        ]);
        $cms->publishPolicy($this->actor, $cookie['id'], $request);

        $public = collect($cms->publicPolicies());
        $this->assertCount(7, $public);
        foreach ($required + $conditional as $type => [$slug, $title]) {
            $row = $public->firstWhere('type', $type);
            $this->assertNotNull($row);
            $this->assertSame($slug, $row['slug']);
            $this->assertSame($title, $row['title']);
            $this->assertSame('2026-09-25', $row['effective_date']);
            $this->assertSame(1, $row['version']);
        }
        $this->assertNull($public->firstWhere('type', 'cookie'));
        $this->assertNull(DB::table('cms_policies')->where('policy_type', 'cookie')->value('footer_destination'));

        $this->reject(fn () => $cms->savePageDraft($this->actor, null, [
            'title' => 'Policy collision',
            'slug' => 'return-refund-policy',
            'content' => '<p>Must not shadow a protected policy.</p>',
        ]));
    }

    private function recentAuthRequest(): Request
    {
        $request = Request::create('/admin/policies/publish', 'POST');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->session()->put('identity_recent_auth_at', now()->timestamp);

        return $request;
    }
}
