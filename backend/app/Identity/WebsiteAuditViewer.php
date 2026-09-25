<?php

namespace App\Identity;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class WebsiteAuditViewer
{
    public function page(Admin $actor, array $input): array
    {
        abort_unless(app(Access::class)->allows($actor, 'website.audit.view'), 403);
        if (array_diff(array_keys($input), ['q', 'method', 'page'])) {
            throw ValidationException::withMessages(['filter' => 'Unexpected Website audit filter.']);
        }
        $filters = validator($input, [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'method' => ['sometimes', 'nullable', 'in:GET,POST,PUT,PATCH,DELETE,SYSTEM'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ])->validate();
        // Only Website/digital actions are visible; no request payloads, tokens or free-form references.
        $http = DB::table('admin_audit_logs')->where(function ($q) {
            $q->where('action', 'like', 'website\_%')
                ->orWhere('action', 'like', 'digital\_%')
                ->orWhere('path', 'like', '/internal/admin/website%')
                ->orWhere('path', 'like', '/internal/admin/platform/pages/%')
                ->orWhere('path', 'like', '/internal/admin/platform/presentation/%')
                ->orWhere('path', 'like', '/internal/admin/platform/website-%')
                ->orWhere('path', 'like', '/internal/admin/platform/media/%')
                ->orWhere('path', 'like', '/internal/admin/platform/software/%')
                ->orWhere('path', 'like', '/internal/admin/digital-operations%');
        })->selectRaw("id, 'http' as source, actor_name, actor_email, action, method, path, status_code, created_at");
        $events = DB::table('identity_audit_events')->where('realm', 'admin')
            ->where(function ($q) {
                $q->where('action', 'like', 'website\_%')->orWhere('action', 'like', 'digital\_%');
            })->selectRaw("id, 'identity' as source, actor_name_snapshot as actor_name, NULL as actor_email, action, NULL as method, NULL as path, NULL as status_code, created_at");
        $records = DB::query()->fromSub($http->unionAll($events), 'audit');
        if (! empty($filters['method'])) {
            $records->where('method', $filters['method']);
        }
        if (! empty($filters['q'])) {
            $search = '%'.addcslashes(trim($filters['q']), '%_\\').'%';
            $records->where(function ($q) use ($search) {
                $q->where('actor_name', 'like', $search)->orWhere('actor_email', 'like', $search)
                    ->orWhere('action', 'like', $search)->orWhere('path', 'like', $search);
            });
        }
        $page = $records->orderByDesc('created_at')->orderByDesc('id')->paginate(50);

        return ['records' => $page->items(), 'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(), 'total' => $page->total(),
            'filters' => array_intersect_key($filters, array_flip(['q', 'method']))];
    }
}
