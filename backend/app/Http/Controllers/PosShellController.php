<?php

namespace App\Http\Controllers;

use App\Models\Admin;
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
