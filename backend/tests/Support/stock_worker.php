<?php

use App\Inventory\InventoryOperations;
use App\Inventory\TransactionalStock;
use App\Models\Outlet;
use App\Models\SuperAdmin;
use App\Sales\SalesOperations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

// Isolated process fixture; never reads credentials from arguments or emits private data.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
$db = DB::selectOne('SELECT DATABASE() db, @@port port, CONNECTION_ID() connection_id');
if (! $app->environment('testing') || $db->db !== 'mobisttech_test' || (int) $db->port !== 13306) {
    exit(3);
}
$input = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
echo json_encode(['ready' => true, 'connection' => $db->connection_id])."\n";
flush();
try {
    $result = DB::transaction(function () use ($input) {
        // Establish an old repeatable-read snapshot before contending on the parent's product lock.
        DB::table('products')->count();
        echo "ATTEMPT\n";
        flush();

        return match ($input['operation']) {
            'reserve' => app(TransactionalStock::class)->reserve($input['id']),
            'sale' => app(TransactionalStock::class)->consumeSale($input['id']),
            'imeis' => app(InventoryOperations::class)->imeis(SuperAdmin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['product'], $input['key'], ['unit_id' => $input['unit'], 'version' => 1, 'imeis' => [1 => 'race-imei-1', 2 => 'race-imei-2']]),
            'return' => app(SalesOperations::class)->acceptReturn(SuperAdmin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['key'], $input['input']),
            default => throw new LogicException('Unknown synthetic operation.'),
        };
    }, 3);
    echo json_encode(['outcome' => 'committed', 'result' => $result])."\n";
} catch (LogicException|UniqueConstraintViolationException $error) {
    echo json_encode(['outcome' => 'rejected', 'class' => $error::class])."\n";
} catch (Throwable $error) {
    echo json_encode(['outcome' => 'error', 'class' => $error::class, 'message' => $error->getMessage()])."\n";
    exit(2);
}
