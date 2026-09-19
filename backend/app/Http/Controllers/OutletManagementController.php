<?php

namespace App\Http\Controllers;

use App\Identity\OutletLifecycleAdministration;
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
