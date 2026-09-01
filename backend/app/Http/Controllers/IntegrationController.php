<?php

namespace App\Http\Controllers;

use App\Backups\BackupService;
use App\Integrations\IntegrationManager;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class IntegrationController extends Controller
{
    public function page(IntegrationManager $manager)
    {
        return Inertia::render('integrations', ['integrations' => $manager->statuses($this->actor())]);
    }

    public function index(IntegrationManager $manager)
    {
        return response()->json(['data' => $manager->statuses($this->actor())]);
    }

    public function connect(string $provider, IntegrationManager $manager)
    {
        return response()->json(['data' => $manager->connect($this->actor(), $provider)]);
    }

    public function callback(Request $request, string $provider, IntegrationManager $manager)
    {
        $data = $manager->callback($this->actor(), $provider, $request->query());

        return $request->expectsJson() ? response()->json(['data' => $data]) : redirect()->route('admin.integrations.page');
    }

    public function test(string $provider, IntegrationManager $manager)
    {
        return response()->json(['data' => $manager->test($this->actor(), $provider)]);
    }

    public function disconnect(string $provider, IntegrationManager $manager)
    {
        return response()->json(['data' => $manager->disconnect($this->actor(), $provider)]);
    }

    public function backupNow(BackupService $backups)
    {
        $id = $backups->request($this->actor(), 'manual');

        return response()->json(['data' => ['backup_id' => $id, 'status' => 'queued']], 202);
    }

    public function backupSettings(Request $request, IntegrationManager $manager)
    {
        return response()->json(['data' => $manager->backupSettings($this->actor(), $request->all())]);
    }

    private function actor(): Admin
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);

        return $actor;
    }
}
