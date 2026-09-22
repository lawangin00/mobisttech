<?php

// CLI-only independent Laravel application worker for disposable W04 race acceptance.
use App\Commerce\WebsitePaymentAdministration;
use App\Models\Admin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (app()->environment() !== 'testing' || DB::connection()->getDatabaseName() !== 'mobisttech_test') {
    fwrite(STDERR, "W04 worker requires the isolated testing database.\n");
    exit(10);
}
[$script, $action, $actorId, $draftId, $marker] = $argv;
$admin = Admin::findOrFail((int) $actorId);
file_put_contents($marker, 'READY');
try {
    $service = app(WebsitePaymentAdministration::class);
    $result = $action === 'draft'
        ? $service->saveDraft($admin, ['cod_enabled' => false])
        : $service->publish($admin, (int) $draftId);
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (HttpExceptionInterface $exception) {
    echo json_encode(['status' => $exception->getStatusCode()], JSON_THROW_ON_ERROR);
}
