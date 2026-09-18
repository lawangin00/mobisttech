<?php

namespace App\Http\Controllers;

use App\Cms\WebsiteModePublication;
use App\Digital\ClientProjectServices;
use App\Digital\DigitalServiceLeads;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class DigitalOperationsController extends Controller
{
    private const PERMISSIONS = [
        'website.services.manage',
        'website.consultations.manage',
        'website.digital-leads.manage',
        'website.digital-projects.manage',
        'website.proposals.approve',
        'website.client-files.manage',
        'website.conversions.view',
    ];

    public function page()
    {
        $actor = $this->actor();
        abort_unless($this->canEnter($actor), 403);

        return Inertia::render('digital-operations', [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
        ]);
    }

    public function index(WebsiteModePublication $modes)
    {
        $actor = $this->actor();
        abort_unless($this->canEnter($actor), 403);
        $permissions = array_values(array_filter(self::PERMISSIONS, fn (string $permission) => $actor->hasPermission($permission)));

        return response()->json(['data' => [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
            'permissions' => $permissions,
            'website_mode' => $modes->publicProfile(),
            'mode_history' => DB::table('site_configuration_revisions')->where('domain', 'website.mode')
                ->orderByDesc('version')->limit(30)->get(['id', 'version', 'state', 'snapshot', 'published_at', 'created_at'])
                ->map(fn ($row) => [
                    'id' => (int) $row->id, 'version' => (int) $row->version, 'state' => $row->state,
                    'mode' => (json_decode($row->snapshot, true) ?: [])['mode'] ?? null,
                    'published_at' => $row->published_at, 'created_at' => $row->created_at,
                ])->all(),
            'services' => $actor->hasPermission('website.services.manage') ? $this->services() : [],
            'consultation' => $actor->hasPermission('website.consultations.manage') ? $this->consultationPayload() : null,
            'leads' => $actor->hasPermission('website.digital-leads.manage') ? $this->leads() : [],
            'projects' => $actor->hasPermission('website.digital-projects.manage') ? $this->projects() : [],
            'lead_owners' => $actor->hasPermission('website.digital-leads.manage')
                ? $this->eligibleAdmins('website.digital-leads.manage') : [],
            'project_owners' => $actor->hasPermission('website.digital-projects.manage')
                ? $this->eligibleAdmins('website.digital-projects.manage') : [],
            'customers' => $actor->hasPermission('website.digital-projects.manage') ? $this->customers() : [],
        ]]);
    }

    public function service(Request $request, DigitalServiceLeads $service)
    {
        return response()->json(['data' => $service->configureService($this->actor(), $request->all())]);
    }

    public function consultation(Request $request, DigitalServiceLeads $service)
    {
        return response()->json(['data' => $service->configureConsultation($this->actor(), $request->all())]);
    }

    public function lead(string $lead, DigitalServiceLeads $service)
    {
        return response()->json(['data' => $service->lead($this->actor(), $lead)]);
    }

    public function leadUpdate(Request $request, string $lead, DigitalServiceLeads $service)
    {
        return response()->json(['data' => $service->transition($this->actor(), $lead, $request->all())]);
    }

    public function leadFile(string $lead, string $file, DigitalServiceLeads $service)
    {
        return $this->download($service->downloadReference($this->actor(), $lead, $file));
    }

    public function projectCreate(Request $request, string $lead, ClientProjectServices $service)
    {
        return response()->json(['data' => $service->createProject($this->actor(), $lead, $request->all())]);
    }

    public function project(string $project, ClientProjectServices $service)
    {
        return response()->json(['data' => $service->adminProject($this->actor(), $project)]);
    }

    public function projectTransition(Request $request, string $project, ClientProjectServices $service)
    {
        return response()->json(['data' => $service->transition($this->actor(), $project, $request->all())]);
    }

    public function proposal(Request $request, string $project, ClientProjectServices $service)
    {
        return response()->json(['data' => $service->createProposal($this->actor(), $project, $request->all())]);
    }

    public function approveProposal(string $proposal, ClientProjectServices $service)
    {
        return response()->json(['data' => $service->approveProposal($this->actor(), $proposal)]);
    }

    public function delivery(Request $request, string $project, ClientProjectServices $service)
    {
        $data = $request->validate([
            'base64' => 'required|string|max:15728640',
            'name' => 'required|string|max:255',
            'proposal_public_id' => 'nullable|uuid',
        ]);
        $contents = base64_decode($data['base64'], true);
        if ($contents === false) {
            throw ValidationException::withMessages(['base64' => 'Private delivery payload is not valid base64.']);
        }

        return response()->json(['data' => $service->uploadDelivery(
            $this->actor(), $project, ['name' => $data['name'], 'contents' => $contents], $data['proposal_public_id'] ?? null,
        )]);
    }

    public function projectFile(string $project, string $file, ClientProjectServices $service)
    {
        return $this->download($service->adminDownload($this->actor(), $project, $file));
    }

    public function conversions(Request $request, ClientProjectServices $service)
    {
        $data = $request->validate([
            'from' => 'required|string|max:80',
            'to' => 'required|string|max:80',
        ]);

        return response()->json(['data' => $service->conversionSummary($this->actor(), $data['from'], $data['to'])]);
    }

    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return $actor->fresh();
    }

    private function canEnter(Admin $actor): bool
    {
        return collect(self::PERMISSIONS)->contains(fn (string $permission) => $actor->hasPermission($permission));
    }

    private function services(): array
    {
        return DB::table('digital_services')->orderBy('sort_order')->orderBy('id')->get()
            ->map(function ($service) {
                return [
                    'slug' => $service->slug,
                    'name' => $service->name,
                    'category' => $service->category,
                    'short_description' => $service->short_description,
                    'description' => $service->description,
                    'price_type' => $service->price_type,
                    'price' => $service->price,
                    'is_active' => (bool) $service->is_active,
                    'sort_order' => (int) $service->sort_order,
                    'packages' => $this->offers('digital_service_packages', (int) $service->id),
                    'addons' => $this->offers('digital_service_addons', (int) $service->id),
                ];
            })->all();
    }

    private function offers(string $table, int $serviceId): array
    {
        return DB::table($table)->where('digital_service_id', $serviceId)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn ($row) => [
                'public_id' => $row->public_id, 'code' => $row->code, 'name' => $row->name,
                'pricing_type' => $row->pricing_type, 'price' => $row->price, 'active' => (bool) $row->active,
                'sort_order' => (int) $row->sort_order, 'version' => (int) $row->version,
            ])->all();
    }

    private function consultationPayload(): array
    {
        $row = DB::table('digital_consultation_settings')->where('id', 1)->first();
        if (! $row) {
            return [
                'enabled' => false, 'timezone' => 'Asia/Karachi', 'weekly_availability' => [],
                'version' => 0, 'external_calendar' => ['enabled' => false, 'provider' => null],
            ];
        }

        return [
            'enabled' => (bool) $row->enabled, 'timezone' => $row->timezone,
            'weekly_availability' => json_decode($row->weekly_availability ?: '[]', true, flags: JSON_THROW_ON_ERROR),
            'version' => (int) $row->version, 'external_calendar' => ['enabled' => false, 'provider' => null],
        ];
    }

    private function leads(): array
    {
        return DB::table('service_requests as r')
            ->join('service_request_details as d', 'd.service_request_id', '=', 'r.id')
            ->leftJoin('digital_services as s', 's.id', '=', 'r.digital_service_id')
            ->leftJoin('admins as a', 'a.id', '=', 'd.assigned_admin_id')
            ->leftJoin('client_projects as p', 'p.service_request_id', '=', 'r.id')
            ->orderByDesc('r.id')->limit(200)
            ->get([
                'r.public_id', 'r.reference', 'r.version', 'r.status', 'r.customer_name', 'r.business_name',
                'r.customer_mobile', 'r.customer_email', 'r.requirements', 'r.preferred_contact',
                's.slug as service_slug', 's.name as service_name',
                'd.project_type', 'd.budget_range', 'd.preferred_timeline', 'd.source', 'd.campaign',
                'd.follow_up_at', 'd.consultation_requested', 'd.consultation_status',
                'd.preferred_timezone', 'd.preferred_window_start_utc', 'd.preferred_window_end_utc',
                'a.public_id as assigned_admin_public_id', 'a.name as assigned_admin_name',
                'p.public_id as project_public_id', 'p.reference as project_reference',
                'r.created_at', 'r.updated_at',
            ])->map(fn ($row) => (array) $row)->all();
    }

    private function projects(): array
    {
        return DB::table('client_projects as p')
            ->leftJoin('digital_services as s', 's.id', '=', 'p.digital_service_id')
            ->leftJoin('service_requests as r', 'r.id', '=', 'p.service_request_id')
            ->leftJoin('users as u', 'u.id', '=', 'p.customer_account_id')
            ->leftJoin('admins as a', 'a.id', '=', 'p.assigned_admin_id')
            ->orderByDesc('p.id')->limit(200)
            ->get([
                'p.public_id', 'p.reference', 'p.title', 'p.status', 'p.version',
                's.slug as service_slug', 's.name as service_name',
                'r.public_id as lead_public_id', 'r.reference as lead_reference',
                'u.public_id as customer_account_public_id', 'u.name as customer_name', 'u.email as customer_email', 'u.mobile as customer_mobile',
                'a.public_id as assigned_admin_public_id', 'a.name as assigned_admin_name',
                'p.created_at', 'p.updated_at',
            ])->map(fn ($row) => (array) $row)->all();
    }

    private function eligibleAdmins(string $permission): array
    {
        return Admin::query()->where('status', false)->whereNull('archived_at')->orderBy('name')->get()
            ->filter(fn (Admin $admin) => $admin->usable() && $admin->hasPermission($permission))
            ->map(fn (Admin $admin) => ['id' => $admin->public_id, 'name' => $admin->name, 'job_title' => $admin->job_title])
            ->values()->all();
    }

    private function customers(): array
    {
        return DB::table('users')->where('is_admin', false)->whereNull('archived_at')->whereNotNull('public_id')
            ->orderBy('name')->limit(500)->get(['public_id', 'name', 'email', 'mobile'])
            ->map(fn ($row) => ['id' => $row->public_id, 'name' => $row->name, 'email' => $row->email, 'mobile' => $row->mobile])
            ->all();
    }

    private function download(array $file)
    {
        $name = str_replace(['"', "\r", "\n"], '', $file['name']);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime_type'],
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Content-Length' => (string) $file['byte_size'],
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
