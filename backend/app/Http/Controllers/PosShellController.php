<?php

namespace App\Http\Controllers;

use App\Identity\AdminProfilePhoto;
use App\Identity\OutletLifecycleAdministration;
use App\Identity\OutletProfileAdministration;
use App\Models\Admin;
use App\Models\Outlet;
use App\Pos\PosConfiguration;
use App\Pos\PosShell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

final class PosShellController extends Controller
{
    public function login(PosConfiguration $configuration)
    {
        $user = Auth::guard('admin')->user();
        if ($user instanceof Admin && $user->usable()) {
            return redirect()->route('admin.pos.home');
        }

        return Inertia::render('pos-login', ['presentation' => $configuration->runtimePresentation()]);
    }

    public function profilePhoto(AdminProfilePhoto $photos)
    {
        return $photos->show($this->profileActor());
    }

    public function uploadProfilePhoto(Request $request, AdminProfilePhoto $photos)
    {
        return response()->json(['data' => $photos->upload($this->profileActor(), $request)]);
    }

    public function removeProfilePhoto(AdminProfilePhoto $photos)
    {
        return response()->json(['data' => $photos->remove($this->profileActor())]);
    }

    public function manageAccount()
    {
        $actor = $this->profileActor();

        return Inertia::render('admin-account', ['offline_owner_recovery_available' => config('identity.offline_owner_recovery.enabled') === true
            && hash_equals((string) config('identity.offline_owner_recovery.owner_admin_public_id'), (string) $actor->public_id)
            && app(OutletLifecycleAdministration::class)->canManage($actor),
            'identity' => [
                'name' => $actor->name, 'email' => $actor->email,
                'job_title' => $actor->job_title, 'roles' => $actor->roleNames(), 'has_photo' => (bool) $actor->profile_photo,
            ]]);
    }

    public function recoveryRequest()
    {
        return $this->recoveryPage('request');
    }

    public function recoveryReset()
    {
        return $this->recoveryPage('reset');
    }

    private function recoveryPage(string $mode)
    {
        $response = Inertia::render('admin-recovery', ['mode' => $mode,
            'offline_owner_recovery_available' => config('identity.offline_owner_recovery.enabled') === true
                && Str::isUuid((string) config('identity.offline_owner_recovery.owner_admin_public_id'))])->toResponse(request());
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
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

    public function home(Request $request, PosShell $shell, PosConfiguration $configuration)
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return Inertia::render('pos-shell', [
            'shell' => $shell->contract($request, $actor),
            'view' => ['kind' => 'home'],
            'presentation' => $configuration->runtimePresentation(),
        ]);
    }

    public function workspace(Request $request, string $area, PosShell $shell, PosConfiguration $configuration)
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        $workspace = $shell->authorizeArea($request, $actor, $area);

        return Inertia::render('pos-shell', [
            'shell' => $shell->contract($request, $actor, $area),
            'view' => ['kind' => 'workspace', 'workspace' => $workspace],
            'presentation' => $configuration->runtimePresentation(),
        ]);
    }
}
