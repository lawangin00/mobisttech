<?php

namespace App\Http\Controllers;

use App\Identity\OutletLifecycleAdministration;
use App\Identity\OutletProfileAdministration;
use App\Models\Outlet;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class OutletManagementController extends Controller
{
    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        return $actor;
    }

    public function page(OutletLifecycleAdministration $service)
    {
        abort_unless($service->canManage($this->actor()), 403);
        return Inertia::render('outlet-management');
    }

    public function index(OutletLifecycleAdministration $service)
    {
        return response()->json(['data' => $service->catalogue($this->actor())]);
    }

    public function profile(string $outlet, OutletProfileAdministration $profiles)
    {
        $target = Outlet::where('public_id', $outlet)->firstOrFail();
        return response()->json(['data' => $profiles->show($this->actor(), $target, true)]);
    }

    public function updateProfile(Request $request, string $outlet, OutletProfileAdministration $profiles)
    {
        $confirmedAt = (int) $request->session()->get('identity_explicit_password_confirmed_at', 0);
        abort_unless($confirmedAt > 0 && now()->timestamp - $confirmedAt < (int) config('identity.sessions.admin.recent_auth_minutes') * 60, 403, 'Confirm your current password first.');
        $target = Outlet::where('public_id', $outlet)->firstOrFail();
        return response()->json(['data' => $profiles->update($this->actor(), $target, $request->all(), true)]);
    }

    public function archivedInvoiceDocument(Request $request, string $outlet, string $invoice,
        OutletLifecycleAdministration $service)
    {
        $data = $request->validate(['password' => ['required', 'string'],
            'purpose' => ['required', 'string', 'min:10', 'max:100']]);
        abort_unless(count($request->all()) === 2, 422, 'Unexpected retrieval fields.');
        $response = response()->json(['data' => $service->archivedInvoiceDocument(
            $this->actor(), $outlet, $invoice, $data['password'], $data['purpose'])]);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }

    public function archivedInvoicePdf(Request $request, string $outlet, string $invoice,
        OutletLifecycleAdministration $service)
    {
        $data = $request->validate(['password' => ['required', 'string'],
            'purpose' => ['required', 'string', 'min:10', 'max:100']]);
        abort_unless(count($request->all()) === 2, 422, 'Unexpected reconstruction fields.');
        $response = response()->json(['data' => $service->archivedInvoicePdf(
            $this->actor(), $outlet, $invoice, $data['password'], $data['purpose'])]);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }

    public function archivedClaimDocument(Request $request, string $outlet, string $claim,
        OutletLifecycleAdministration $service)
    {
        $data = $request->validate(['password' => ['required', 'string'],
            'purpose' => ['required', 'string', 'min:10', 'max:100']]);
        abort_unless(count($request->all()) === 2, 422, 'Unexpected retrieval fields.');
        $response = response()->json(['data' => $service->archivedClaimDocument(
            $this->actor(), $outlet, $claim, $data['password'], $data['purpose'])]);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }

    public function archivedHistory(string $outlet, OutletLifecycleAdministration $service)
    {
        return response()->json(['data' => $service->archivedHistory($this->actor(), $outlet)]);
    }

    public function store(Request $request, OutletLifecycleAdministration $service)
    {
        return response()->json(['data' => $service->create($this->actor(), $request->all())], 201);
    }

    public function archive(Request $request, string $outlet, OutletLifecycleAdministration $service)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1']]);
        abort_unless(count($request->all()) === 1, 422, 'Unexpected archive fields.');
        return response()->json(['data' => $service->archive($this->actor(), $outlet, (int) $data['version'])]);
    }
}
