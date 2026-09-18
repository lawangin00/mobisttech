<?php

namespace App\Http\Controllers;

use App\Addendum\ResetDomains;
use App\Addendum\ResetRetention;
use App\Identity\Access;
use App\Identity\RealmSessionPolicy;
use App\Models\Admin;
use App\Operations\GuardedResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

final class ResetAdministrationController extends Controller
{
    public function page()
    {
        $actor = $this->actor();
        $this->authorize($actor, 'system.reset.preview');

        return Inertia::render('reset-administration', [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
        ]);
    }

    public function index(Request $request, ResetDomains $domains, RealmSessionPolicy $sessions)
    {
        $actor = $this->actor();
        $this->authorize($actor, 'system.reset.preview');

        $levels = collect(['transactional', 'business', 'factory'])->mapWithKeys(function (string $level) use ($actor, $domains) {
            return [$level => [
                'label' => match ($level) {
                    'transactional' => 'Transactional Data Reset',
                    'business' => 'Business Data Reset',
                    'factory' => 'Factory Reset',
                },
                'description' => match ($level) {
                    'transactional' => 'Clears selected transactional/operational history while preserving inventory, identities, configuration, branding and bootstrap access.',
                    'business' => 'Clears selected transactional plus business/inventory/customer/content domains while preserving authorized bootstrap access and protected recovery evidence.',
                    'factory' => 'Clears the complete approved factory scope and retains only the protected minimum access/recovery bootstrap defined by reset policy.',
                },
                'permission' => 'system.reset.'.$level,
                'can_execute' => app(Access::class)->allows($actor, 'system.reset.'.$level),
                'confirmation_required' => (string) config('reset.confirmation.'.$level),
                'available_domains' => $domains->available($level),
                'factory_scope_locked' => $level === 'factory',
            ]];
        })->all();

        return response()->json(['data' => [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
            'levels' => $levels,
            'domain_labels' => collect(ResetDomains::MAP)->keys()->mapWithKeys(fn (string $domain) => [
                $domain => str($domain)->replace('_', ' ')->title()->toString(),
            ])->all(),
            'recent_authentication' => $request->hasSession() && $sessions->recentlyAuthenticated($request),
            'preview_ttl_minutes' => max(1, (int) config('reset.preview_ttl_minutes', 10)),
            'execution_enabled_here' => in_array(app()->environment(), config('reset.execution_environments', []), true),
            'production_hold' => ! in_array('production', config('reset.execution_environments', []), true),
            'minimum_bootstrap' => [
                'access_tables' => ResetRetention::GROUPS['bootstrap'],
                'recovery_evidence_tables' => ResetRetention::GROUPS['preserved_evidence'],
                'note' => 'Reset policy preserves authorized administrator/outlet/permission/role access plus durable backup, reset and audit evidence. Factory configuration clearing does not authorize schema destruction.',
            ],
            'operations' => $this->operations(),
            'audit' => $this->audit(),
        ]]);
    }

    public function preview(Request $request, GuardedResetService $service)
    {
        $data = $request->validate([
            'level' => 'required|string|in:transactional,business,factory',
            'domains' => 'required|array|min:1',
            'domains.*' => 'required|string|max:80',
        ]);

        return response()->json(['data' => $service->preview(
            $this->actor(), $request, $data['level'], $data['domains'],
        )]);
    }

    public function execute(Request $request, string $operation, GuardedResetService $service)
    {
        $data = $request->validate([
            'confirmation' => 'required|string|max:120',
        ]);

        return response()->json(['data' => $service->execute(
            $this->actor(), $request, $operation, $data['confirmation'],
        )]);
    }

    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return $actor->fresh();
    }

    private function authorize(Admin $actor, string $permission): void
    {
        abort_unless(app(Access::class)->allows($actor, $permission), 403);
    }

    private function operations(): array
    {
        if (! Schema::hasTable('reset_operations')) {
            return [];
        }

        return DB::table('reset_operations as r')
            ->leftJoin('backup_records as b', 'b.id', '=', 'r.backup_record_id')
            ->leftJoin('backup_manifests as m', 'm.backup_record_id', '=', 'b.id')
            ->leftJoinSub(
                DB::table('backup_restore_rehearsals')
                    ->select('backup_record_id', DB::raw('MAX(id) as latest_id'))
                    ->groupBy('backup_record_id'),
                'rh',
                'rh.backup_record_id',
                '=',
                'b.id',
            )
            ->leftJoin('backup_restore_rehearsals as rr', 'rr.id', '=', 'rh.latest_id')
            ->orderByDesc('r.id')
            ->limit(100)
            ->get([
                'r.public_id', 'r.level', 'r.selected_domains', 'r.record_count', 'r.file_count',
                'r.status', 'r.failure_code', 'r.started_at', 'r.completed_at', 'r.result', 'r.created_at',
                'b.id as backup_record_id', 'b.status as backup_status', 'b.filename as backup_filename',
                'b.size_bytes as backup_size_bytes', 'b.checksum as backup_checksum', 'b.completed_at as backup_completed_at',
                'm.verified_at as manifest_verified_at', 'rr.status as rehearsal_status',
                'rr.failure_code as rehearsal_failure_code', 'rr.checked_at as rehearsal_checked_at',
            ])
            ->map(fn ($row) => [
                'public_id' => $row->public_id,
                'level' => $row->level,
                'domains' => json_decode($row->selected_domains ?: '[]', true),
                'record_count' => (int) $row->record_count,
                'file_count' => (int) $row->file_count,
                'status' => $row->status,
                'failure_code' => $row->failure_code,
                'started_at' => $row->started_at,
                'completed_at' => $row->completed_at,
                'result' => $row->result ? json_decode($row->result, true) : null,
                'created_at' => $row->created_at,
                'backup' => $row->backup_record_id ? [
                    'record_id' => (int) $row->backup_record_id,
                    'status' => $row->backup_status,
                    'filename' => $row->backup_filename,
                    'size_bytes' => $row->backup_size_bytes ? (int) $row->backup_size_bytes : null,
                    'checksum' => $row->backup_checksum,
                    'completed_at' => $row->backup_completed_at,
                    'manifest_verified_at' => $row->manifest_verified_at,
                    'restore_rehearsal' => [
                        'status' => $row->rehearsal_status,
                        'failure_code' => $row->rehearsal_failure_code,
                        'checked_at' => $row->rehearsal_checked_at,
                    ],
                ] : null,
            ])->all();
    }

    private function audit(): array
    {
        if (! Schema::hasTable('admin_audit_logs')) {
            return [];
        }

        return DB::table('admin_audit_logs')
            ->whereIn('action', [
                'reset_preview_created', 'reset_backup_verified', 'reset_backup_failed',
                'reset_cleanup_pending', 'reset_completed',
            ])
            ->orderByDesc('id')->limit(100)
            ->get(['id', 'actor_name', 'actor_email', 'action', 'payload', 'status_code', 'created_at'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'actor_name' => $row->actor_name,
                'actor_email' => $row->actor_email,
                'action' => $row->action,
                'payload' => json_decode($row->payload ?: '{}', true),
                'status_code' => (int) $row->status_code,
                'created_at' => $row->created_at,
            ])->all();
    }
}
