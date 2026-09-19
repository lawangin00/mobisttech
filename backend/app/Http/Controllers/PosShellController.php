<?php

namespace App\Http\Controllers;

use App\Identity\OutletProfileAdministration;
use App\Models\Admin;
use App\Models\Outlet;
use App\Pos\PosShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class PosShellController extends Controller
{
    public function login()
    {
        $user = Auth::guard('admin')->user();
        if ($user instanceof Admin && $user->usable()) {
            return redirect()->route('admin.pos.home');
        }

        return Inertia::render('pos-login');
    }

    public function outletProfile(Request $request, OutletProfileAdministration $service)
    {
        return response()->json(['data' => $service->show($this->profileActor(), $this->selectedOutlet($request))]);
    }

    public function updateOutletProfile(Request $request, OutletProfileAdministration $service)
    {
        return response()->json(['data' => $service->update($this->profileActor(), $this->selectedOutlet($request), $request->all())]);
    }

    private function profileActor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        return $actor;
    }

    private function selectedOutlet(Request $request): Outlet
    {
        $id = (int) $request->session()->get('active_outlet_id', 0);
        abort_unless($id > 0, 403, 'Select an outlet first.');
        return Outlet::whereKey($id)->firstOrFail();
    }

    public function home(Request $request, PosShell $shell)
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return Inertia::render('pos-shell', [
            'shell' => $shell->contract($request, $actor),
            'view' => ['kind' => 'home'],
        ]);
    }

    public function workspace(Request $request, string $area, PosShell $shell)
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        $workspace = $shell->authorizeArea($request, $actor, $area);

        return Inertia::render('pos-shell', [
            'shell' => $shell->contract($request, $actor, $area),
            'view' => ['kind' => 'workspace', 'workspace' => $workspace],
        ]);
    }
}
