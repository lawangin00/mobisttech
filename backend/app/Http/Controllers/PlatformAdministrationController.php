<?php

namespace App\Http\Controllers;

use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use App\Documents\CanonicalDocuments;
use App\Identity\Access;
use App\Identity\TeamMemberAdministration;
use App\Integrations\IntegrationManager;
use App\Loyalty\LoyaltyServices;
use App\Models\Admin;
use App\Models\Outlet;
use App\Payments\PosPaymentOperations;
use App\Promotions\PromotionServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class PlatformAdministrationController extends Controller
{
    private const TEMPLATE_KEYS = [
        'invoice_whatsapp', 'warranty_whatsapp', 'invoice_email_subject',
        'invoice_email_body', 'warranty_email_subject', 'warranty_email_body',
    ];

    public function page()
    {
        $actor = $this->actor();
        abort_unless($this->canEnter($actor), 403);

        return Inertia::render('platform-admin', [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
        ]);
    }

    public function index(
        Request $request,
        WebsiteModePublication $modes,
        TeamMemberAdministration $team,
        IntegrationManager $integrations,
        PosPaymentOperations $payments,
    ) {
        $actor = $this->actor();
        abort_unless($this->canEnter($actor), 403);
        $outlet = $this->activeOutlet($request, $actor);

        $permissions = collect(Admin::PERMISSIONS)->keys()->filter(
            fn (string $permission) => app(Access::class)->allows($actor, $permission, $outlet),
        )->values()->all();

        $modeRevisions = DB::table('site_configuration_revisions')->where('domain', 'website.mode')
            ->orderByDesc('version')->limit(30)->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id, 'version' => (int) $row->version, 'state' => $row->state,
                'mode' => (json_decode($row->snapshot, true) ?: [])['mode'] ?? null,
                'published_at' => $row->published_at, 'created_at' => $row->created_at,
            ])->all();

        $policies = DB::table('cms_policies as p')
            ->leftJoin('cms_policy_revisions as r', 'r.id', '=', 'p.current_revision_id')
            ->orderBy('p.policy_type')->get([
                'p.policy_type', 'p.title', 'p.requirement', 'p.footer_destination',
                'r.id as revision_id', 'r.version', 'r.state', 'r.effective_date',
            ])->map(fn ($row) => (array) $row)->all();

        $policyHistory = DB::table('cms_policy_revisions')->orderByDesc('id')->limit(50)->get([
            'id', 'policy_type', 'version', 'state', 'effective_date', 'approval_state',
            'factual_review_state', 'published_at', 'created_at',
        ])->map(fn ($row) => (array) $row)->all();

        $templates = DB::table('document_template_revisions as d')
            ->whereIn('d.template_key', self::TEMPLATE_KEYS)
            ->whereRaw('d.version = (select max(x.version) from document_template_revisions x where x.template_key = d.template_key)')
            ->orderBy('d.template_key')->get([
                'd.public_id as template_id', 'd.template_key', 'd.version', 'd.document_type',
                'd.channel', 'd.template_part', 'd.template_text',
            ])->map(fn ($row) => (array) $row)->all();

        $software = DB::table('software_products as s')
            ->leftJoin('software_product_revisions as r', 'r.id', '=', 's.current_revision_id')
            ->leftJoin('software_releases as rel', 'rel.id', '=', 's.current_release_id')
            ->orderBy('s.name')->get([
                's.public_id', 's.name', 's.slug', 's.lifecycle_state', 's.archived_at',
                'r.id as current_revision_id', 'r.version as current_revision_version',
                'rel.version as current_version',
            ])->map(function ($row) {
                $productId = DB::table('software_products')->where('public_id', $row->public_id)->value('id');

                return [
                    'id' => $row->public_id, 'name' => $row->name, 'slug' => $row->slug,
                    'status' => $row->lifecycle_state, 'archived_at' => $row->archived_at,
                    'current_revision_id' => $row->current_revision_id ? (int) $row->current_revision_id : null,
                    'current_revision_version' => $row->current_revision_version ? (int) $row->current_revision_version : null,
                    'current_version' => $row->current_version,
                    'revisions' => DB::table('software_product_revisions')->where('software_product_id', $productId)
                        ->orderByDesc('version')->limit(20)->get(['id', 'version', 'state', 'created_at', 'published_at'])
                        ->map(fn ($x) => (array) $x)->all(),
                    'releases' => DB::table('software_releases')->where('software_product_id', $productId)
                        ->orderByDesc('id')->limit(20)->get(['id', 'public_id', 'version', 'state', 'release_date', 'summary', 'published_at'])
                        ->map(fn ($x) => (array) $x)->all(),
                ];
            })->all();

        $latestPromotion = DB::table('promotions')->orderByDesc('id')->first();
        $loyalty = DB::table('loyalty_configurations')->orderByDesc('id')->first();

        return response()->json(['data' => [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
            'permissions' => $permissions,
            'outlet' => $outlet ? ['id' => $outlet->public_id, 'name' => $outlet->name] : null,
            'website_mode' => $modes->publicProfile(), 'mode_revisions' => $modeRevisions,
            'policies' => $policies, 'policy_history' => $policyHistory, 'templates' => $templates,
            'software' => $software,
            'team_members' => $actor->hasPermission('team-members.view') ? $team->members($actor) : [],
            'roles' => $actor->hasPermission('team-members.view') ? $team->catalogue($actor) : [],
            'integrations' => $this->canIntegrations($actor) ? $integrations->statuses($actor) : [],
            'payment_destinations' => $outlet && $actor->hasPermission('config.payments.manage')
                ? $payments->destinations($actor, $outlet, false) : [],
            'promotion' => $latestPromotion ? (array) $latestPromotion : null,
            'loyalty' => $loyalty ? (array) $loyalty : null,
        ]]);
    }

    public function modeDraft(Request $request, WebsiteModePublication $service)
    {
        $data = $request->validate(['mode' => 'required|in:digital_only,hybrid,commerce_only']);

        return response()->json(['data' => $service->saveDraft($this->actor(), $data['mode'])]);
    }

    public function modePreview(int $revision, WebsiteModePublication $service)
    {
        return response()->json(['data' => $service->preview($this->actor(), $revision)]);
    }

    public function modePublish(int $revision, WebsiteModePublication $service)
    {
        return response()->json(['data' => $service->publish($this->actor(), $revision)]);
    }

    public function modeRollback(int $revision, WebsiteModePublication $service)
    {
        return response()->json(['data' => $service->rollback($this->actor(), $revision)]);
    }

    public function policyDraft(Request $request, string $type, WebsiteCms $service)
    {
        return response()->json(['data' => $service->savePolicyDraft($this->actor(), $type, $request->all())]);
    }

    public function policyPublish(Request $request, int $revision, WebsiteCms $service)
    {
        return response()->json(['data' => $service->publishPolicy($this->actor(), $revision, $request)]);
    }

    public function policyRollback(Request $request, int $revision, WebsiteCms $service)
    {
        return response()->json(['data' => $service->rollbackPolicy($this->actor(), $revision, $request)]);
    }

    public function template(Request $request, string $key, CanonicalDocuments $service)
    {
        $data = $request->validate(['text' => 'required|string|max:10000']);

        return response()->json(['data' => $service->updateTemplate($this->actor(), $key, $data['text'])]);
    }

    public function destinationCreate(Request $request, PosPaymentOperations $service)
    {
        [$actor, $outlet] = [$this->actor(), $this->requiredOutlet($request)];

        return response()->json(['data' => $service->createDestination($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function destinationUpdate(Request $request, string $destination, PosPaymentOperations $service)
    {
        [$actor, $outlet] = [$this->actor(), $this->requiredOutlet($request)];

        return response()->json(['data' => $service->updateDestination($actor, $outlet, $destination, $this->key($request), $request->all())]);
    }

    public function promotion(Request $request, PromotionServices $service)
    {
        return response()->json(['data' => $service->configure($this->actor(), $request->input('id'), $request->except('id'))]);
    }

    public function loyalty(Request $request, LoyaltyServices $service)
    {
        return response()->json(['data' => (array) $service->configure($this->actor(), $request->all())]);
    }

    public function softwareDraft(Request $request, WebsiteCms $service, ?string $software = null)
    {
        return response()->json(['data' => $service->saveSoftwareDraft($this->actor(), $software, $request->all())]);
    }

    public function softwarePublish(int $revision, WebsiteCms $service)
    {
        return response()->json(['data' => $service->publishSoftware($this->actor(), $revision)]);
    }

    public function softwareRollback(int $revision, WebsiteCms $service)
    {
        return response()->json(['data' => $service->rollbackSoftware($this->actor(), $revision)]);
    }

    public function releaseDraft(Request $request, string $software, WebsiteCms $service)
    {
        return response()->json(['data' => $service->saveReleaseDraft($this->actor(), $software, $request->all())]);
    }

    public function releasePublish(int $release, WebsiteCms $service)
    {
        return response()->json(['data' => $service->publishRelease($this->actor(), $release)]);
    }

    public function softwareArchive(string $software, WebsiteCms $service)
    {
        return response()->json(['data' => $service->archiveSoftware($this->actor(), $software)]);
    }

    public function softwareSlug(Request $request, string $software, WebsiteCms $service)
    {
        $data = $request->validate(['slug' => 'required|string|max:160', 'reason' => 'required|string|max:1000']);

        return response()->json(['data' => $service->changeSoftwareSlug($this->actor(), $software, $data['slug'], $data['reason'])]);
    }

    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return $actor->fresh();
    }

    private function canEnter(Admin $actor): bool
    {
        $prefixes = ['website.', 'config.', 'team-members.', 'admin.'];

        return collect($actor->effectivePermissions())->contains(
            fn (string $permission) => collect($prefixes)->contains(fn (string $prefix) => str_starts_with($permission, $prefix)),
        );
    }

    private function canIntegrations(Admin $actor): bool
    {
        return $actor->hasPermission('admin.integrations.manage') || $actor->hasPermission('config.integrations-backups.manage');
    }

    private function activeOutlet(Request $request, Admin $actor): ?Outlet
    {
        $id = (int) $request->session()->get('active_outlet_id', 0);

        return $actor->shops()->whereKey($id)->where('outlets.status', false)->whereNull('outlets.archived_at')->first();
    }

    private function requiredOutlet(Request $request): Outlet
    {
        $outlet = $this->activeOutlet($request, $this->actor());
        abort_unless($outlet instanceof Outlet, 403, 'Choose an active outlet first.');

        return $outlet;
    }

    private function key(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency-Key is required.']);
        }

        return $key;
    }
}
