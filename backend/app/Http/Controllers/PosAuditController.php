<?php

namespace App\Http\Controllers;

use App\Identity\PosAuditViewer;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class PosAuditController extends Controller
{
    public function index(Request $request, PosAuditViewer $viewer)
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        $data = $viewer->page($actor, $request->query());
        $response = Inertia::render('pos-audit-viewer', $data)->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
