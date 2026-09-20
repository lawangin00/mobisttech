<?php

use App\Cash\CashSessionOperations;
use App\Inventory\InventoryOperations;
use App\Inventory\StocktakeOperations;
use App\Inventory\StockTransferOperations;
use App\Inventory\TransactionalStock;
use App\Loyalty\LoyaltyServices;
use App\Models\Admin;
use App\Models\Outlet;
use App\Payments\PosPaymentOperations;
use App\Procurement\SupplierProcurement;
use App\Repairs\PaidRepairOperations;
use App\Sales\SalesOperations;
use App\Warranty\ClaimOperations;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        if (isset($input['barrier_product'])) {
            DB::table('products')->where('id', $input['barrier_product'])->lockForUpdate()->firstOrFail();
        }

        return match ($input['operation']) {
            'reserve' => app(TransactionalStock::class)->reserve($input['id']),
            'sale' => app(TransactionalStock::class)->consumeSale($input['id']),
            'confirm_reserved_sale' => (function () use ($input) {
                $movement = app(TransactionalStock::class)->consumeSale($input['sale'], $input['line']);
                DB::table('reservations')->where('id', $input['reservation'])->update([
                    'state' => 'confirmed', 'confirmed_at' => now(), 'updated_at' => now(),
                ]);

                return $movement;
            })(),
            'release_reservation' => app(TransactionalStock::class)->release($input['reservation']),
            'imeis' => app(InventoryOperations::class)->imeis(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['product'], $input['key'], ['unit_id' => $input['unit'], 'version' => 1, 'imeis' => [1 => 'race-imei-1', 2 => 'race-imei-2']]),
            'return' => app(SalesOperations::class)->acceptReturn(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['key'], $input['input']),
            'claim' => app(ClaimOperations::class)->open(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['key'], $input['input']),
            'procurement_receive' => app(SupplierProcurement::class)->receive(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['order'], $input['key'], $input['input']),
            'stocktake_approve' => app(StocktakeOperations::class)->approve(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['stocktake'], $input['key'], $input['input']),
            'transfer_receive' => app(StockTransferOperations::class)->receive(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['transfer'], $input['key'], $input['input']),
            'pos_payment_sale' => app(PosPaymentOperations::class)->sell(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['key'], $input['input']),
            'cash_close' => app(CashSessionOperations::class)->close(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['session'], $input['key'], $input['input']),
            'repair_parts' => app(PaidRepairOperations::class)->consumeParts(Admin::findOrFail($input['actor']), Outlet::findOrFail($input['outlet']),
                $input['repair'], $input['key']),
            'loyalty_claim' => app(LoyaltyServices::class)->claim('pos', $input['customer'], $input['owner'], $input['points'], $input['max'], $input['bases']),
            'loyalty_earn' => app(LoyaltyServices::class)->earnInvoice($input['invoice']),
            'loyalty_reverse' => app(LoyaltyServices::class)->reverseReturn($input['return']),
            default => throw new LogicException('Unknown synthetic operation.'),
        };
    }, 3);
    echo json_encode(['outcome' => 'committed', 'result' => $result])."\n";
} catch (HttpException|LogicException|UniqueConstraintViolationException|ValidationException $error) {
    echo json_encode(['outcome' => 'rejected', 'class' => $error::class])."\n";
} catch (Throwable $error) {
    echo json_encode(['outcome' => 'error', 'class' => $error::class, 'message' => $error->getMessage()])."\n";
    exit(2);
}
