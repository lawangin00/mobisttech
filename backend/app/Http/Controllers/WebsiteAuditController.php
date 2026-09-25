<?php

namespace App\Http\Controllers;

use App\Identity\WebsiteAuditViewer;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class WebsiteAuditController extends Controller
{
    public function index(Request $request, WebsiteAuditViewer $viewer)
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        $response = Inertia::render('website-audit-viewer', $viewer->page($actor, $request->query()))->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
