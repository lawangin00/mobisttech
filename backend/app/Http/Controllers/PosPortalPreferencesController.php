<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Pos\PortalPreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class PosPortalPreferencesController extends Controller
{
    public function index(Request $request, PortalPreferences $preferences)
    {
        $data = $preferences->catalogue($this->actor());
        $response = Inertia::render('pos-portal-preferences', $data)->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function update(Request $request, PortalPreferences $preferences)
    {
        return response()->json(['data' => $preferences->update($this->actor(), $request->all())])
            ->header('Cache-Control', 'private, no-store');
    }

    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return $actor;
    }
}
