<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Pos\DashboardReportPreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class PosDashboardReportPreferencesController extends Controller
{
    public function index(Request $request, DashboardReportPreferences $preferences)
    {
        $response = Inertia::render('pos-dashboard-report-preferences', $preferences->catalogue($this->actor()))->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function update(Request $request, DashboardReportPreferences $preferences)
    {
        return response()->json(['data' => ['sections' => $preferences->update($this->actor(), $request->all())]])
            ->header('Cache-Control', 'private, no-store');
    }

    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return $actor;
    }
}
