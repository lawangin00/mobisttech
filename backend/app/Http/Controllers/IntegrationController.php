<?php

namespace App\Http\Controllers;

use App\Backups\BackupService;
use App\Identity\Access;
use App\Integrations\IntegrationManager;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

final class IntegrationController extends Controller
{
    public function page(IntegrationManager $manager)
    {
        $actor = $this->actor();
        $canIntegrations = app(Access::class)->allows($actor, 'admin.integrations.manage');
        $canBackups = app(Access::class)->allows($actor, 'backups.manage');
        abort_unless($canIntegrations || $canBackups, 403);

        return Inertia::render('integrations', [
            'integrations' => $canIntegrations ? $manager->statuses($actor) : [],
            'can_manage_integrations' => $canIntegrations,
            'can_manage_backups' => $canBackups,
        ]);
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

    public function backupHistory(BackupService $backups)
    {
        return response()->json(['data' => $backups->history($this->actor())])
            ->header('Cache-Control', 'private, no-store');
    }

    public function backupDownload(int $backup, BackupService $backups)
    {
        $file = $backups->readLocal($this->actor(), $backup);

        return response($file['bytes'], 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.basename($file['filename']).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function backupDelete(int $backup, BackupService $backups)
    {
        $backups->deleteLocal($this->actor(), $backup);

        return response()->json(['data' => ['deleted' => true]]);
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
