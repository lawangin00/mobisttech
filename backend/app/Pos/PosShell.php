<?php

namespace App\Pos;

use App\Identity\Access;
use App\Identity\RealmSessionPolicy;
use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Http\Request;

final class PosShell
{
    private const AREAS = [
        'sales' => ['label' => 'Sales', 'description' => 'Create and complete in-store sales.', 'permission' => 'shop.sales'],
        'inventory' => ['label' => 'Inventory', 'description' => 'Products, stock, units and receiving.', 'permission' => 'shop.inventory'],
        'invoices' => ['label' => 'Invoices & customers', 'description' => 'Sale history, invoices and customer records.', 'permission' => 'shop.invoices'],
        'warranty' => ['label' => 'Warranty', 'description' => 'Warranty intake and supporting records.', 'permission' => 'shop.warranty'],
        'claims' => ['label' => 'Claims', 'description' => 'Warranty claim lifecycle and history.', 'permission' => 'shop.claims'],
        'profile' => ['label' => 'Outlet profile', 'description' => 'Edit the assigned outlet business contact and display details.', 'permission' => 'shop.profile'],
        'reports' => ['label' => 'Reports', 'description' => 'Outlet-scoped business reports and figures.', 'permission' => 'reports.view'],
        'operations' => ['label' => 'Operations', 'description' => 'Cash closing, trade-ins and paid repairs.',
            'permissions_any' => ['shop.cash', 'shop.cash.approve', 'shop.trade-in', 'shop.repairs', 'shop.payments.reconcile']],
    ];

    public function contract(Request $request, Admin $actor, ?string $area = null): array
    {
        $outlets = $actor->shops()->where('outlets.status', false)->whereNull('outlets.archived_at')
            ->orderBy('outlets.name')->get(['outlets.id', 'outlets.public_id', 'outlets.name']);
        $active = $this->activeOutlet($request, $actor, $outlets->all());
        $navigation = [];
        if ($actor->hasPermission('shops.enter') && $outlets->isNotEmpty()) {
            foreach (self::AREAS as $key => $definition) {
                if ($this->navigationAllowed($actor, $definition)) {
                    $navigation[] = [
                        'key' => $key,
                        'label' => $definition['label'],
                        'description' => $definition['description'],
                        'href' => '/internal/admin/pos/workspace/'.$key,
                    ];
                }
            }
        }

        return [
            'identity' => [
                'id' => $actor->public_id,
                'name' => $actor->name,
                'email' => $actor->email,
                'job_title' => $actor->job_title,
                'roles' => $actor->roleNames(),
                'permissions' => $actor->effectivePermissions(),
            ],
            'outlets' => $outlets->map(fn ($outlet) => ['id' => $outlet->public_id, 'name' => $outlet->name])->values()->all(),
            'active_outlet' => $active ? ['id' => $active->public_id, 'name' => $active->name] : null,
            'navigation' => $navigation,
            'session_policy' => app(RealmSessionPolicy::class)->publicContract('admin'),
            'session_state' => app(RealmSessionPolicy::class)->publicState($request, 'admin', $actor),
            'current_area' => $area,
            'workspace' => $area ? $this->area($area) : null,
        ];
    }

    public function authorizeArea(Request $request, Admin $actor, string $area): array
    {
        $definition = $this->area($area);
        $active = $this->activeOutlet($request, $actor,
            $actor->shops()->where('outlets.status', false)->whereNull('outlets.archived_at')->get()->all());
        abort_unless($active && $this->areaAllowed($actor, $active, $definition), 403);

        return $definition;
    }

    public function area(string $area): array
    {
        abort_unless(array_key_exists($area, self::AREAS), 404);

        return ['key' => $area, ...self::AREAS[$area]];
    }

    private function navigationAllowed(Admin $actor, array $definition): bool
    {
        if (isset($definition['permission'])) {
            return $actor->hasPermission($definition['permission']);
        }

        return collect($definition['permissions_any'] ?? [])->contains(
            fn (string $permission) => $actor->hasPermission($permission),
        );
    }

    private function areaAllowed(Admin $actor, Outlet $outlet, array $definition): bool
    {
        if (isset($definition['permission'])) {
            return app(Access::class)->allows($actor, $definition['permission'], $outlet);
        }

        return collect($definition['permissions_any'] ?? [])->contains(
            fn (string $permission) => app(Access::class)->allows($actor, $permission, $outlet),
        );
    }

    private function activeOutlet(Request $request, Admin $actor, array $outlets): ?Outlet
    {
        $activeId = (int) $request->session()->get('active_outlet_id', 0);
        if ($activeId <= 0 || ! $actor->hasPermission('shops.enter')) {
            return null;
        }
        foreach ($outlets as $outlet) {
            if ((int) $outlet->id === $activeId) {
                return $outlet;
            }
        }
        $request->session()->forget(['active_outlet_id', 'operator_admin_id']);

        return null;
    }
}
