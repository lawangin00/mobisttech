<?php

namespace App\Identity;

use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PosAuditViewer
{
    public function authorize(Admin $actor): void
    {
        // Source SuperAdmin audit visibility maps to the protected unified Admin owner.
        abort_unless(app(OutletLifecycleAdministration::class)->canManage($actor), 403);
    }

    public function page(Admin $actor, array $input): array
    {
        $this->authorize($actor);
        if (array_diff(array_keys($input), ['q', 'actor_type', 'method', 'outlet', 'page'])) {
            throw ValidationException::withMessages(['filter' => 'Unexpected audit filter.']);
        }
        $filter = validator($input, [
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'actor_type' => ['sometimes', 'nullable', 'string', 'max:40'],
            'method' => ['sometimes', 'nullable', 'in:GET,POST,PUT,PATCH,DELETE,SYSTEM'],
            'outlet' => ['sometimes', 'nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ])->validate();
        $query = DB::table('pos_audit_logs as log')
            ->leftJoin('outlets as outlet', 'outlet.id', '=', 'log.outlet_id');
        if (! empty($filter['q'])) {
            $search = trim($filter['q']);
            if ($search !== '') {
                $query->where(function ($where) use ($search) {
                    foreach (['log.actor_name', 'log.actor_email', 'log.action', 'log.path'] as $column) {
                        $where->orWhere($column, 'like', '%'.addcslashes($search, '%_\\').'%');
                    }
                });
            }
        }
        if (! empty($filter['actor_type'])) {
            $query->where('log.actor_type', $filter['actor_type']);
        }
        if (! empty($filter['method'])) {
            $query->where('log.method', $filter['method']);
        }
        if (! empty($filter['outlet'])) {
            $id = Outlet::where('public_id', $filter['outlet'])->value('id');
            abort_if($id === null, 404);
            $query->where('log.outlet_id', $id);
        }
        $page = $query->orderByDesc('log.id')->select([
            'log.id', 'log.actor_type', 'log.actor_name', 'log.actor_email',
            'log.action', 'log.method', 'log.path', 'log.status_code',
            'log.created_at', 'outlet.name as outlet_name', 'outlet.outlet_code',
        ])->paginate(60);

        return ['records' => $page->items(), 'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(), 'total' => $page->total(),
            'filters' => array_intersect_key($filter, array_flip(['q', 'actor_type', 'method', 'outlet'])),
            'outlets' => Outlet::orderBy('outlet_code')->get(['public_id', 'outlet_code', 'name'])
                ->map(fn (Outlet $outlet) => ['id' => $outlet->public_id,
                    'outlet_code' => $outlet->outlet_code, 'name' => $outlet->name])->all()];
    }
}
