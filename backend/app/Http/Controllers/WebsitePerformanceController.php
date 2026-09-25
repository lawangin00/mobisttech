<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Reporting\WebsitePerformanceReports;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class WebsitePerformanceController extends Controller
{
    public function page(WebsitePerformanceReports $reports)
    {
        $actor = $this->actor();
        $reports->authorize($actor);

        return Inertia::render('website-performance', [
            'identity' => ['name' => $actor->name, 'job_title' => $actor->job_title],
        ]);
    }

    public function summary(Request $request, WebsitePerformanceReports $reports)
    {
        $data = $reports->summary($this->actor(), $request->query());

        return response()->json(['data' => $data])->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    public function csv(Request $request, WebsitePerformanceReports $reports)
    {
        $data = $reports->csv($this->actor(), $request->query());

        return response()->json(['data' => $data])->withHeaders(['Cache-Control' => 'private, no-store']);
    }

    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return $actor;
    }
}
