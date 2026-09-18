<?php

namespace App\Digital;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Addendum\WebsiteCapabilities;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Infrastructure\PrivateObjects;
use App\Models\Admin;
use App\Models\CustomerAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class ClientProjectServices
{
    private const PROJECT_STATUSES = ['request', 'discussion', 'proposal', 'approved', 'in_progress', 'review', 'delivered', 'completed', 'closed'];

    private const FILE_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'];

    public function __construct(
        private readonly Access $access,
        private readonly WebsiteCapabilities $capabilities,
        private readonly FinancialReferences $financial,
        private readonly PrivateObjects $objects,
    ) {}

    public function createProject(Admin $actor, string $serviceRequestPublicId, array $input): array
    {
        $this->authorize($actor, 'website.digital-projects.manage');
        $this->fields($input, ['title', 'customer_account_public_id', 'assigned_admin_public_id']);
        $data = Validator::make($input, [
            'title' => 'required|string|max:255',
            'customer_account_public_id' => 'nullable|uuid',
            'assigned_admin_public_id' => 'nullable|uuid',
        ])->validate();

        return DB::transaction(function () use ($actor, $serviceRequestPublicId, $data) {
            $request = DB::table('service_requests')->where('public_id', $serviceRequestPublicId)->lockForUpdate()->firstOrFail();
            abort_if(in_array($request->status, ['lost', 'closed'], true), 409, 'Closed lead cannot create a project.');
            abort_if(DB::table('client_projects')->where('service_request_id', $request->id)->exists(), 409, 'Lead already has a project.');
            $customer = $this->customer($data['customer_account_public_id'] ?? null);
            $assignee = $this->projectAdmin($data['assigned_admin_public_id'] ?? null);
            $publicId = (string) Str::uuid();
            $reference = 'PRJ-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
            $projectId = (int) DB::table('client_projects')->insertGetId([
                'public_id' => $publicId, 'reference' => $reference, 'service_request_id' => $request->id,
                'digital_service_id' => $request->digital_service_id, 'customer_account_id' => $customer?->id,
                'assigned_admin_id' => $assignee?->id, 'title' => trim($data['title']), 'status' => 'request',
                'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($projectId, 'project_created', $actor->id, null, [
                'project_id' => $publicId, 'reference' => $reference, 'status' => 'request',
                'service_request_id' => $serviceRequestPublicId, 'customer_linked' => $customer !== null,
            ]);
            IdentityAudit::record('admin', $actor->id, 'digital_project_created', 'client-project:'.$projectId);

            return $this->adminPayload($projectId);
        });
    }

    public function createProposal(Admin $actor, string $projectPublicId, array $input): array
    {
        $this->authorize($actor, 'website.digital-projects.manage');
        $this->fields($input, ['version', 'title', 'scope', 'deliverables', 'amount', 'valid_until', 'milestones']);
        $data = Validator::make($input, [
            'version' => 'required|integer|min:1', 'title' => 'required|string|max:255',
            'scope' => 'required|string|max:20000', 'deliverables' => 'required|array|min:1|max:100',
            'deliverables.*' => 'required|string|max:1000', 'amount' => 'required|string|max:30',
            'valid_until' => 'required|string|max:80', 'milestones' => 'nullable|array|max:20',
        ])->validate();
        $amount = MoneySnapshot::amount($data['amount']);
        $validUntil = $this->futureInstant($data['valid_until'], 'valid_until');
        $schedule = $this->schedule($data['milestones'] ?? [], $amount);
        $snapshot = $this->canonical([
            'title' => trim($data['title']), 'scope' => trim($data['scope']),
            'deliverables' => array_values(array_map('trim', $data['deliverables'])),
            'amount' => $amount, 'currency' => 'PKR', 'valid_until' => $validUntil->toISOString(), 'schedule' => $schedule,
        ]);
        $digest = $this->digest($snapshot);

        return DB::transaction(function () use ($actor, $projectPublicId, $data, $amount, $validUntil, $snapshot, $digest) {
            $project = DB::table('client_projects')->where('public_id', $projectPublicId)->lockForUpdate()->firstOrFail();
            abort_unless((int) $project->version === (int) $data['version'], 409, 'Project version changed; reload before editing.');
            abort_if(in_array($project->status, ['completed', 'closed'], true), 409, 'Completed or closed project cannot receive a proposal.');
            $revision = ((int) DB::table('project_proposals')->where('client_project_id', $project->id)->lockForUpdate()->max('revision_no')) + 1;
            $proposalPublicId = (string) Str::uuid();
            $proposalId = (int) DB::table('project_proposals')->insertGetId([
                'public_id' => $proposalPublicId, 'client_project_id' => $project->id, 'revision_no' => $revision,
                'state' => 'draft', 'title' => trim($data['title']), 'amount' => $amount, 'currency' => 'PKR',
                'valid_until' => $validUntil, 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'snapshot_sha256' => $digest, 'created_by_admin_id' => $actor->id, 'created_at' => now(),
            ]);
            $nextStatus = in_array($project->status, ['request', 'discussion'], true) ? 'proposal' : $project->status;
            DB::table('client_projects')->where('id', $project->id)->update([
                'status' => $nextStatus, 'version' => (int) $project->version + 1, 'updated_at' => now(),
            ]);
            $this->event((int) $project->id, 'proposal_drafted', $actor->id, null, [
                'proposal_id' => $proposalPublicId, 'revision' => $revision, 'amount' => $amount,
                'valid_until' => $validUntil->toISOString(), 'snapshot_sha256' => $digest,
            ]);
            IdentityAudit::record('admin', $actor->id, 'digital_proposal_drafted', 'project-proposal:'.$proposalId);

            return $this->proposalPayload($proposalId, true);
        });
    }

    public function approveProposal(Admin $actor, string $proposalPublicId): array
    {
        $this->authorize($actor, 'website.proposals.approve');

        return DB::transaction(function () use ($actor, $proposalPublicId) {
            $proposal = DB::table('project_proposals')->where('public_id', $proposalPublicId)->lockForUpdate()->firstOrFail();
            $project = DB::table('client_projects')->where('id', $proposal->client_project_id)->lockForUpdate()->firstOrFail();
            if ($proposal->state === 'approved') {
                return $this->proposalPayload((int) $proposal->id, true);
            }
            abort_unless($proposal->state === 'draft', 409, 'Only a draft proposal may be approved.');
            $snapshot = $this->verifiedProposalSnapshot($proposal);
            abort_if(now()->gte(CarbonImmutable::parse($proposal->valid_until)), 409, 'Expired proposal cannot be approved.');
            abort_if(in_array($project->status, ['in_progress', 'review', 'delivered', 'completed', 'closed'], true), 409, 'Project lifecycle no longer permits proposal approval.');
            abort_unless($project->customer_account_id, 409, 'Explicit client account ownership is required before approval.');
            $customer = CustomerAccount::query()->whereKey($project->customer_account_id)->firstOrFail();
            abort_unless($customer->usable(), 409, 'Linked client account is unavailable.');

            $previous = DB::table('project_proposals')->where('client_project_id', $project->id)
                ->where('state', 'approved')->where('id', '<>', $proposal->id)->lockForUpdate()->first();
            if ($previous) {
                $paid = DB::table('project_proposal_milestones as pm')
                    ->join('project_milestone_identities as mi', 'mi.id', '=', 'pm.milestone_id')
                    ->where('pm.project_proposal_id', $previous->id)->whereNotNull('mi.paid_payment_id')->exists();
                $activePayment = DB::table('project_proposal_milestones as pm')
                    ->join('order_item_milestones as oim', 'oim.milestone_id', '=', 'pm.milestone_id')
                    ->join('order_items as oi', 'oi.id', '=', 'oim.order_item_id')
                    ->join('payments as pay', 'pay.order_id', '=', 'oi.order_id')
                    ->where('pm.project_proposal_id', $previous->id)
                    ->whereIn('pay.status', ['pending', 'unknown', 'paid', 'paid_reconciliation'])->exists();
                abort_if($paid || $activePayment, 409, 'A proposal with paid or active payment history cannot be superseded.');
                DB::table('project_proposals')->where('id', $previous->id)->update(['state' => 'superseded']);
                DB::table('project_quotes')->where('id', $previous->quote_id)->update(['status' => 'superseded', 'updated_at' => now()]);
            }
            $request = DB::table('service_requests')->where('id', $project->service_request_id)->firstOrFail();
            $quotePublicId = (string) Str::uuid();
            $quoteReference = 'QTE-'.now()->format('ymd').'-'.Str::upper(Str::random(10));
            $quoteId = (int) DB::table('project_quotes')->insertGetId([
                'reference' => $quoteReference, 'digital_service_id' => $project->digital_service_id,
                'client_name' => $customer->name, 'customer_mobile' => $customer->mobile ?: $request->customer_mobile,
                'customer_email' => $customer->email, 'title' => $proposal->title, 'description' => $snapshot['scope'],
                'amount' => $proposal->amount, 'currency' => 'PKR', 'status' => 'approved', 'expires_at' => $proposal->valid_until,
                'public_id' => $quotePublicId, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($snapshot['schedule'] as $index => $item) {
                $scopeHash = hash('sha256', $proposal->snapshot_sha256.'|'.$this->digest($item));
                $milestoneId = $this->financial->milestone($quoteId, MoneySnapshot::milestone(
                    (string) Str::uuid(), $quotePublicId, $index + 1, $item['amount'], $scopeHash
                ));
                DB::table('project_proposal_milestones')->insert([
                    'milestone_id' => $milestoneId, 'project_proposal_id' => $proposal->id,
                    'kind' => $item['kind'], 'label' => $item['label'],
                    'due_at' => $item['due_at'] ? CarbonImmutable::parse($item['due_at'])->utc() : null, 'created_at' => now(),
                ]);
            }
            DB::table('project_proposals')->where('id', $proposal->id)->update([
                'state' => 'approved', 'quote_id' => $quoteId, 'approved_by_admin_id' => $actor->id, 'approved_at' => now(),
            ]);
            DB::table('client_projects')->where('id', $project->id)->update([
                'status' => 'approved', 'version' => (int) $project->version + 1, 'updated_at' => now(),
            ]);
            $this->event((int) $project->id, 'proposal_approved', $actor->id, null, [
                'proposal_id' => $proposal->public_id, 'quote_id' => $quotePublicId,
                'amount' => $proposal->amount, 'milestone_count' => count($snapshot['schedule']),
                'snapshot_sha256' => $proposal->snapshot_sha256,
            ]);
            IdentityAudit::record('admin', $actor->id, 'digital_proposal_approved', 'project-proposal:'.$proposal->id);

            return $this->proposalPayload((int) $proposal->id, true);
        });
    }

    public function transition(Admin $actor, string $projectPublicId, array $input): array
    {
        $this->authorize($actor, 'website.digital-projects.manage');
        $this->fields($input, ['version', 'status']);
        $data = Validator::make($input, [
            'version' => 'required|integer|min:1', 'status' => 'required|string|in:'.implode(',', self::PROJECT_STATUSES),
        ])->validate();

        return DB::transaction(function () use ($actor, $projectPublicId, $data) {
            $project = DB::table('client_projects')->where('public_id', $projectPublicId)->lockForUpdate()->firstOrFail();
            abort_unless((int) $project->version === (int) $data['version'], 409, 'Project version changed; reload before transition.');
            $this->assertTransition($project->status, $data['status']);
            if ($data['status'] === 'completed') {
                $approved = DB::table('project_proposals')->where('client_project_id', $project->id)->where('state', 'approved')->first();
                abort_unless($approved, 409, 'Completed project requires an approved proposal.');
                $unpaid = DB::table('project_proposal_milestones as pm')->join('project_milestone_identities as mi', 'mi.id', '=', 'pm.milestone_id')
                    ->where('pm.project_proposal_id', $approved->id)->whereNull('mi.paid_payment_id')->exists();
                abort_if($unpaid, 409, 'Completed project cannot retain unpaid approved milestones.');
            }
            DB::table('client_projects')->where('id', $project->id)->update([
                'status' => $data['status'], 'version' => (int) $project->version + 1, 'updated_at' => now(),
            ]);
            $this->event((int) $project->id, 'project_status_changed', $actor->id, null, [
                'from_status' => $project->status, 'to_status' => $data['status'], 'version' => (int) $project->version + 1,
            ]);
            IdentityAudit::record('admin', $actor->id, 'digital_project_status_changed', 'client-project:'.$project->id);

            return $this->adminPayload((int) $project->id);
        });
    }

    public function uploadReference(CustomerAccount $customer, string $projectPublicId, array $file): array
    {
        $project = $this->ownedProject($customer, $projectPublicId);
        abort_if($project->status === 'closed', 409, 'Closed project cannot accept new reference files.');
        $validated = $this->file($file);
        $id = (string) Str::uuid();
        $key = 'client-files/'.$id.'.bin';
        $this->objects->put($key, $validated['contents'], $validated['sha256']);
        DB::transaction(function () use ($project, $customer, $validated, $id, $key) {
            DB::table('project_files')->insert([
                'id' => $id, 'client_project_id' => $project->id, 'file_type' => 'reference', 'object_key' => $key,
                'original_name' => $validated['name'], 'mime_type' => $validated['mime'], 'byte_size' => $validated['size'],
                'sha256' => $validated['sha256'], 'uploaded_by_customer_account_id' => $customer->id,
                'retention_until' => now()->addYears(2), 'created_at' => now(),
            ]);
            $this->event((int) $project->id, 'reference_file_added', null, $customer->id, [
                'file_id' => $id, 'name' => $validated['name'], 'sha256' => $validated['sha256'],
            ]);
        });

        return $this->filePayload($id);
    }

    public function uploadDelivery(Admin $actor, string $projectPublicId, array $file, ?string $proposalPublicId = null): array
    {
        $this->authorize($actor, 'website.client-files.manage');
        $project = DB::table('client_projects')->where('public_id', $projectPublicId)->firstOrFail();
        abort_unless(in_array($project->status, ['approved', 'in_progress', 'review', 'delivered', 'completed'], true), 409, 'Delivery files require an approved project lifecycle.');
        $proposalId = null;
        if ($proposalPublicId !== null) {
            $proposalId = DB::table('project_proposals')->where('public_id', $proposalPublicId)
                ->where('client_project_id', $project->id)->value('id') ?? abort(404);
        }
        $validated = $this->file($file);
        $id = (string) Str::uuid();
        $key = 'client-files/'.$id.'.bin';
        $this->objects->put($key, $validated['contents'], $validated['sha256']);
        DB::transaction(function () use ($project, $proposalId, $actor, $validated, $id, $key) {
            DB::table('project_files')->insert([
                'id' => $id, 'client_project_id' => $project->id, 'project_proposal_id' => $proposalId,
                'file_type' => 'delivery', 'object_key' => $key, 'original_name' => $validated['name'],
                'mime_type' => $validated['mime'], 'byte_size' => $validated['size'], 'sha256' => $validated['sha256'],
                'uploaded_by_admin_id' => $actor->id, 'retention_until' => now()->addYears(2), 'created_at' => now(),
            ]);
            $this->event((int) $project->id, 'delivery_file_added', $actor->id, null, [
                'file_id' => $id, 'name' => $validated['name'], 'sha256' => $validated['sha256'],
            ]);
            IdentityAudit::record('admin', $actor->id, 'digital_delivery_file_added', 'project-file:'.$id);
        });

        return $this->filePayload($id);
    }

    public function customerProjects(CustomerAccount $customer): array
    {
        abort_unless($customer->usable(), 404);
        $this->capabilities->assertHistoricalAllowed('project.read');

        return DB::table('client_projects as p')
            ->leftJoin('digital_services as s', 's.id', '=', 'p.digital_service_id')
            ->where('p.customer_account_id', $customer->id)
            ->orderByDesc('p.updated_at')->orderByDesc('p.id')
            ->get(['p.public_id', 'p.reference', 'p.title', 'p.status', 'p.version', 'p.updated_at',
                's.slug as service_slug', 's.name as service_name'])
            ->map(fn ($row) => [
                'public_id' => $row->public_id, 'reference' => $row->reference, 'title' => $row->title,
                'status' => $row->status, 'version' => (int) $row->version, 'updated_at' => $row->updated_at,
                'service' => ['slug' => $row->service_slug, 'name' => $row->service_name],
            ])->all();
    }

    public function portal(CustomerAccount $customer, string $projectPublicId): array
    {
        $project = $this->ownedProject($customer, $projectPublicId);

        return $this->publicPayload((int) $project->id);
    }

    public function download(CustomerAccount $customer, string $projectPublicId, string $fileId): array
    {
        $project = $this->ownedProject($customer, $projectPublicId);
        $file = DB::table('project_files')->where('id', $fileId)->where('client_project_id', $project->id)->firstOrFail();
        $contents = $this->objects->get($file->object_key);
        abort_unless(hash_equals($file->sha256, hash('sha256', $contents)), 500, 'Private project file integrity check failed.');
        DB::transaction(fn () => $this->event((int) $project->id, 'project_file_downloaded', null, $customer->id, [
            'file_id' => $file->id, 'file_type' => $file->file_type,
        ]));

        return $this->filePayload($file->id) + ['contents' => $contents];
    }

    public function adminDownload(Admin $actor, string $projectPublicId, string $fileId): array
    {
        $this->authorize($actor, 'website.client-files.manage');
        $project = DB::table('client_projects')->where('public_id', $projectPublicId)->firstOrFail();
        $file = DB::table('project_files')->where('id', $fileId)->where('client_project_id', $project->id)->firstOrFail();
        $contents = $this->objects->get($file->object_key);
        abort_unless(hash_equals($file->sha256, hash('sha256', $contents)), 500, 'Private project file integrity check failed.');
        DB::transaction(fn () => $this->event((int) $project->id, 'project_file_downloaded', $actor->id, null, [
            'file_id' => $file->id, 'file_type' => $file->file_type,
        ]));
        IdentityAudit::record('admin', $actor->id, 'digital_project_file_downloaded', 'project-file:'.$file->id);

        return $this->filePayload($file->id) + ['contents' => $contents];
    }

    public function adminProject(Admin $actor, string $projectPublicId): array
    {
        $this->authorize($actor, 'website.digital-projects.manage');
        $id = DB::table('client_projects')->where('public_id', $projectPublicId)->value('id') ?? abort(404);

        return $this->adminPayload((int) $id);
    }

    public function conversionSummary(Admin $actor, string $from, string $to): array
    {
        $this->authorize($actor, 'website.conversions.view');
        $start = $this->instant($from, 'from');
        $end = $this->instant($to, 'to');
        abort_unless($end->gt($start), 422, 'Conversion reporting end must be after start.');
        abort_if($end->diffInDays($start) > 366, 422, 'Conversion reporting range is limited to 366 days.');
        $leadBase = DB::table('service_requests')->whereBetween('created_at', [$start, $end]);
        $projectBase = DB::table('client_projects')->whereBetween('created_at', [$start, $end]);
        $approvedBase = DB::table('project_proposals')->whereBetween('approved_at', [$start, $end]);
        $paidBase = DB::table('project_milestone_identities')->whereBetween('paid_at', [$start, $end]);
        $service = DB::table('service_requests as r')->leftJoin('digital_services as s', 's.id', '=', 'r.digital_service_id')
            ->leftJoin('client_projects as p', 'p.service_request_id', '=', 'r.id')->whereBetween('r.created_at', [$start, $end])
            ->groupBy('s.slug')->orderBy('s.slug')->selectRaw('COALESCE(s.slug, ?) as service, COUNT(r.id) as leads, COUNT(p.id) as projects', ['unassigned'])
            ->get()->map(fn ($row) => ['service' => $row->service, 'leads' => (int) $row->leads, 'projects' => (int) $row->projects])->all();
        $source = DB::table('service_requests as r')->join('service_request_details as d', 'd.service_request_id', '=', 'r.id')
            ->leftJoin('client_projects as p', 'p.service_request_id', '=', 'r.id')->whereBetween('r.created_at', [$start, $end])
            ->groupBy('d.source')->orderBy('d.source')->selectRaw('COALESCE(d.source, ?) as source, COUNT(r.id) as leads, COUNT(p.id) as projects', ['direct'])
            ->get()->map(fn ($row) => ['source' => $row->source, 'leads' => (int) $row->leads, 'projects' => (int) $row->projects])->all();

        return ['from' => $start->toISOString(), 'to' => $end->toISOString(), 'aggregate_only' => true,
            'totals' => ['leads' => $leadBase->count(), 'projects' => $projectBase->count(),
                'approved_proposals' => $approvedBase->count(), 'paid_milestones' => $paidBase->count(),
                'completed_projects' => DB::table('client_projects')->where('status', 'completed')->whereBetween('updated_at', [$start, $end])->count()],
            'by_service' => $service, 'by_source' => $source];
    }

    private function publicPayload(int $projectId): array
    {
        $project = DB::table('client_projects as p')->leftJoin('digital_services as s', 's.id', '=', 'p.digital_service_id')
            ->where('p.id', $projectId)->select('p.*', 's.slug as service_slug', 's.name as service_name')->firstOrFail();
        $proposals = DB::table('project_proposals')->where('client_project_id', $projectId)
            ->whereIn('state', ['approved', 'superseded', 'expired'])->orderBy('revision_no')->get()
            ->map(fn ($row) => $this->proposalPayload((int) $row->id, false))->all();
        $files = DB::table('project_files')->where('client_project_id', $projectId)->orderBy('created_at')->get()
            ->map(fn ($row) => $this->filePayload($row->id))->all();
        $events = DB::table('project_events')->where('client_project_id', $projectId)
            ->whereIn('event_type', ['proposal_approved', 'project_status_changed', 'reference_file_added', 'delivery_file_added'])
            ->orderBy('id')->get()->map(fn ($row) => ['type' => $row->event_type,
                'snapshot' => $this->publicEventSnapshot($row->event_type, json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR)),
                'occurred_at' => $row->occurred_at])->all();

        return ['public_id' => $project->public_id, 'reference' => $project->reference, 'title' => $project->title,
            'status' => $project->status, 'version' => (int) $project->version,
            'service' => ['slug' => $project->service_slug, 'name' => $project->service_name],
            'proposals' => $proposals, 'files' => $files, 'history' => $events];
    }

    private function adminPayload(int $projectId): array
    {
        $payload = $this->publicPayload($projectId);
        $payload['proposals'] = DB::table('project_proposals')->where('client_project_id', $projectId)->orderBy('revision_no')->get()
            ->map(fn ($row) => $this->proposalPayload((int) $row->id, true))->all();
        $project = DB::table('client_projects')->where('id', $projectId)->firstOrFail();
        $payload['customer_account_public_id'] = $project->customer_account_id ? DB::table('users')->where('id', $project->customer_account_id)->value('public_id') : null;
        $payload['assigned_admin_public_id'] = $project->assigned_admin_id ? DB::table('admins')->where('id', $project->assigned_admin_id)->value('public_id') : null;

        return $payload;
    }

    private function proposalPayload(int $proposalId, bool $includeDraft): array
    {
        $proposal = DB::table('project_proposals')->where('id', $proposalId)->firstOrFail();
        abort_if(! $includeDraft && $proposal->state === 'draft', 404);
        $snapshot = json_decode($proposal->snapshot, true, flags: JSON_THROW_ON_ERROR);
        $quote = $proposal->quote_id ? DB::table('project_quotes')->where('id', $proposal->quote_id)->firstOrFail() : null;
        $milestones = DB::table('project_proposal_milestones as pm')
            ->join('project_milestone_identities as mi', 'mi.id', '=', 'pm.milestone_id')
            ->leftJoin('payments as pay', 'pay.id', '=', 'mi.paid_payment_id')
            ->where('pm.project_proposal_id', $proposalId)->orderBy('mi.sequence')
            ->select('pm.milestone_id', 'pm.kind', 'pm.label', 'pm.due_at', 'mi.sequence', 'mi.approved_amount', 'mi.paid_at', 'pay.status as payment_status')
            ->get()->map(function ($row) use ($proposal, $quote) {
                $payable = $proposal->state === 'approved' && $quote?->status === 'approved' && $row->paid_at === null
                    && ($quote->expires_at === null || now()->lt($quote->expires_at));

                return ['id' => $row->milestone_id, 'sequence' => (int) $row->sequence, 'kind' => $row->kind,
                    'label' => $row->label, 'amount' => $row->approved_amount, 'currency' => 'PKR', 'due_at' => $row->due_at,
                    'paid_at' => $row->paid_at, 'payment_status' => $row->payment_status ?? 'unpaid', 'payable' => $payable];
            })->all();

        return ['public_id' => $proposal->public_id, 'revision' => (int) $proposal->revision_no, 'state' => $proposal->state,
            'title' => $proposal->title, 'amount' => $proposal->amount, 'currency' => $proposal->currency,
            'valid_until' => $proposal->valid_until, 'scope' => $snapshot['scope'], 'deliverables' => $snapshot['deliverables'],
            'schedule' => $snapshot['schedule'], 'snapshot_sha256' => $proposal->snapshot_sha256,
            'quote' => $quote ? ['public_id' => $quote->public_id, 'reference' => $quote->reference, 'status' => $quote->status,
                'expires_at' => $quote->expires_at, 'paid_at' => $quote->paid_at] : null, 'milestones' => $milestones];
    }

    private function ownedProject(CustomerAccount $customer, string $publicId): object
    {
        abort_unless($customer->usable(), 404);
        $this->capabilities->assertHistoricalAllowed('project.read');

        return DB::table('client_projects')->where('public_id', $publicId)
            ->where('customer_account_id', $customer->id)->first() ?? abort(404);
    }

    private function filePayload(string $id): array
    {
        $file = DB::table('project_files')->where('id', $id)->firstOrFail();

        return ['id' => $file->id, 'type' => $file->file_type, 'name' => $file->original_name,
            'mime_type' => $file->mime_type, 'byte_size' => (int) $file->byte_size, 'sha256' => $file->sha256,
            'retention_until' => $file->retention_until, 'created_at' => $file->created_at];
    }

    private function event(int $projectId, string $type, ?int $adminId, ?int $customerId, array $snapshot): void
    {
        $canonical = $this->canonical($snapshot);
        $json = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        DB::table('project_events')->insert([
            'client_project_id' => $projectId, 'event_type' => $type, 'actor_admin_id' => $adminId,
            'actor_customer_account_id' => $customerId, 'snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json),
            'occurred_at' => now(),
        ]);
    }

    private function schedule(array $rows, string $total): array
    {
        if ($rows === []) {
            return [['kind' => 'final', 'label' => 'Final payment', 'amount' => $total, 'due_at' => null]];
        }
        $clean = [];
        $sum = '0.00';
        $finals = 0;
        $deposits = 0;
        foreach ($rows as $row) {
            abort_unless(is_array($row), 422, 'Milestone schedule rows must be objects.');
            $this->fields($row, ['kind', 'label', 'amount', 'due_at']);
            $data = Validator::make($row, [
                'kind' => 'required|string|in:deposit,milestone,final', 'label' => 'required|string|max:190',
                'amount' => 'required|string|max:30', 'due_at' => 'nullable|string|max:80',
            ])->validate();
            $amount = MoneySnapshot::amount($data['amount']);
            $due = isset($data['due_at']) ? $this->futureInstant($data['due_at'], 'due_at')->toISOString() : null;
            $finals += $data['kind'] === 'final' ? 1 : 0;
            $deposits += $data['kind'] === 'deposit' ? 1 : 0;
            $sum = bcadd($sum, $amount, 2);
            $clean[] = ['kind' => $data['kind'], 'label' => trim($data['label']), 'amount' => $amount, 'due_at' => $due];
        }
        abort_unless($finals === 1 && $deposits <= 1, 422, 'Schedule requires exactly one final payment and at most one deposit.');
        abort_unless($clean[array_key_last($clean)]['kind'] === 'final', 422, 'Final payment must be the last schedule item.');
        if ($deposits === 1) {
            abort_unless($clean[0]['kind'] === 'deposit', 422, 'Deposit must be the first schedule item.');
        }
        abort_unless(bccomp($sum, $total, 2) === 0, 422, 'Milestone schedule must equal the proposal amount exactly.');

        return $clean;
    }

    private function publicEventSnapshot(string $type, array $snapshot): array
    {
        $allowed = match ($type) {
            'proposal_approved' => ['proposal_id', 'quote_id', 'amount', 'milestone_count', 'snapshot_sha256'],
            'project_status_changed' => ['from_status', 'to_status', 'version'],
            'reference_file_added', 'delivery_file_added' => ['file_id', 'name', 'sha256'],
            default => [],
        };

        return array_intersect_key($snapshot, array_flip($allowed));
    }

    private function file(array $file): array
    {
        $this->fields($file, ['name', 'contents']);
        $data = Validator::make($file, ['name' => 'required|string|max:255', 'contents' => 'required|string'])->validate();
        $name = trim(basename(str_replace('\\', '/', $data['name'])));
        abort_if($name === '' || preg_match('/[\x00-\x1F\x7F]/', $name), 422, 'Invalid project filename.');
        $contents = $data['contents'];
        $size = strlen($contents);
        abort_if($size < 1 || $size > 10 * 1024 * 1024, 422, 'Project file size limit exceeded.');
        $mime = strtolower((string) ((new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: ''));
        abort_unless(in_array($mime, self::FILE_MIMES, true), 422, 'Unsupported project file type.');
        if (str_starts_with($mime, 'image/')) {
            abort_unless(@getimagesizefromstring($contents) !== false, 422, 'Invalid project image.');
        }

        return ['name' => $name, 'contents' => $contents, 'size' => $size, 'mime' => $mime, 'sha256' => hash('sha256', $contents)];
    }

    private function customer(?string $publicId): ?CustomerAccount
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }
        $customer = CustomerAccount::query()->where('public_id', $publicId)->firstOrFail();
        abort_unless($customer->usable(), 422, 'Linked client account is unavailable.');

        return $customer;
    }

    private function projectAdmin(?string $publicId): ?Admin
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }
        $admin = Admin::query()->where('public_id', $publicId)->firstOrFail();
        abort_unless($admin->usable() && $admin->hasPermission('website.digital-projects.manage'), 422, 'Assigned project owner is not eligible.');

        return $admin;
    }

    private function futureInstant(string $value, string $field): CarbonImmutable
    {
        $instant = $this->instant($value, $field);
        abort_unless($instant->gt(now()), 422, $field.' must be in the future.');

        return $instant;
    }

    private function instant(string $value, string $field): CarbonImmutable
    {
        abort_unless((bool) preg_match('/(?:Z|[+-][0-9]{2}:[0-9]{2})\z/', $value), 422, $field.' must include a timezone offset.');
        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (\Throwable) {
            abort(422, 'Invalid '.$field.' timestamp.');
        }
    }

    private function assertTransition(string $from, string $to): void
    {
        $allowed = [
            'request' => ['discussion', 'proposal', 'closed'],
            'discussion' => ['proposal', 'closed'],
            'proposal' => ['discussion', 'closed'],
            'approved' => ['in_progress', 'closed'],
            'in_progress' => ['review', 'delivered', 'closed'],
            'review' => ['in_progress', 'delivered', 'closed'],
            'delivered' => ['review', 'completed', 'closed'],
            'completed' => [], 'closed' => [],
        ];
        abort_unless(in_array($to, $allowed[$from] ?? [], true), 409, 'Invalid project lifecycle transition.');
    }

    private function authorize(Admin $actor, string $permission): void
    {
        abort_unless($this->access->allows($actor, $permission), 403);
    }

    private function fields(array $input, array $allowed): void
    {
        abort_if(array_diff(array_keys($input), $allowed), 422, 'Unexpected project-service input fields.');
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonical($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }

    private function verifiedProposalSnapshot(object $proposal): array
    {
        try {
            $snapshot = json_decode((string) $proposal->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $snapshotExpiry = CarbonImmutable::parse((string) ($snapshot['valid_until'] ?? ''))->utc();
            $proposalExpiry = CarbonImmutable::parse((string) $proposal->valid_until)->utc();
            $matches = isset($snapshot['title'], $snapshot['amount'], $snapshot['currency'], $snapshot['scope'], $snapshot['schedule'])
                && hash_equals((string) $proposal->snapshot_sha256, $this->digest($snapshot))
                && (string) $proposal->title === (string) $snapshot['title']
                && bccomp((string) $proposal->amount, (string) $snapshot['amount'], 2) === 0
                && (string) $proposal->currency === (string) $snapshot['currency']
                && $proposalExpiry->equalTo($snapshotExpiry);
        } catch (\Throwable) {
            abort(409, 'Proposal snapshot integrity check failed.');
        }
        abort_unless($matches, 409, 'Proposal snapshot integrity check failed.');

        return $snapshot;
    }

    private function digest(array $value): string
    {
        return hash('sha256', json_encode($this->canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
