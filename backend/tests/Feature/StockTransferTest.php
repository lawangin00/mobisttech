<?php

namespace Tests\Feature;

use App\Catalog\ProductDefinitions;
use App\Inventory\StockLedger;
use App\Inventory\StockTransferOperations;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockUnit;
use App\Sales\SalesOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_quantity_transfer_partial_receive_and_reject_preserve_custody_and_balanced_movements(): void
    {
        $source = $this->product();
        [$destination, $target] = $this->destination($source);
        $this->acquire($source, 5);
        $service = app(StockTransferOperations::class);
        $created = $service->create($this->actor, $this->outlet, 'quantity-create', [
            'destination_outlet_id' => $destination->public_id,
            'lines' => [['source_product_id' => $source->public_id, 'destination_product_id' => $target->public_id, 'quantity' => 3]],
        ]);
        $dispatch = $service->dispatch($this->actor, $this->outlet, $created['transfer_id'], 'quantity-dispatch', ['transfer_version' => 1]);
        $snapshot = DB::transaction(fn () => app(StockLedger::class)->snapshot($source->id));
        $this->assertSame(5, $snapshot['on_hand']);
        $this->assertSame(3, $snapshot['held']);
        $this->assertSame(2, $snapshot['available']);
        $this->reject(fn () => app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $source->public_id, 'quantity' => 3]],
        ]));
        $line = $service->details($this->actor, $created['transfer_id'])['lines'][0];
        $partial = $service->receive($this->actor, $destination, $created['transfer_id'], 'quantity-partial', [
            'transfer_version' => $dispatch['version'],
            'lines' => [['line_id' => $line['line_id'], 'receive_quantity' => 2, 'reject_quantity' => 0]],
        ]);
        $this->assertSame('partially_received', $partial['status']);
        $this->assertSame(1, $partial['remaining_quantity']);
        $final = $service->receive($this->actor, $destination, $created['transfer_id'], 'quantity-final', [
            'transfer_version' => $partial['version'],
            'lines' => [['line_id' => $line['line_id'], 'receive_quantity' => 0, 'reject_quantity' => 1]],
        ]);
        $this->assertSame('partially_received', $final['status']);
        $this->assertSame(0, $final['remaining_quantity']);
        $this->assertSame(3, $source->fresh()->qty);
        $this->assertSame(2, $target->fresh()->qty);
        $this->assertSame(0, DB::table('inventory_custody_holds')->whereNull('released_at')->count());
        $this->assertSame(-2, (int) DB::table('stock_movements')->where('product_id', $source->id)->where('type', 'transfer_out')->sum('quantity_change'));
        $this->assertSame(2, (int) DB::table('stock_movements')->where('product_id', $target->id)->where('type', 'transfer_in')->sum('quantity_change'));
        $receipt = DB::table('stock_transfer_receipts')->orderByDesc('id')->first();
        $this->assertSame(hash('sha256', $receipt->receipt_snapshot), $receipt->snapshot_sha256);
    }

    public function test_serialized_receive_preserves_imei_identity_via_successor_lineage(): void
    {
        $source = $this->product(true);
        [$destination, $target] = $this->destination($source);
        $this->acquire($source);
        $unit = StockUnit::where('product_id', $source->id)->firstOrFail();
        $this->imeis($source, $unit, [1 => '356900000000001', 2 => '356900000000002']);
        $service = app(StockTransferOperations::class);
        $created = $service->create($this->actor, $this->outlet, 'serial-create', [
            'destination_outlet_id' => $destination->public_id,
            'lines' => [['source_product_id' => $source->public_id, 'destination_product_id' => $target->public_id, 'unit_ids' => [$unit->public_id]]],
        ]);
        $dispatch = $service->dispatch($this->actor, $this->outlet, $created['transfer_id'], 'serial-dispatch', ['transfer_version' => 1]);
        $this->assertSame(1, DB::table('inventory_custody_holds')->whereNull('released_at')->count());
        $this->assertSame(2, DB::table('active_imeis')->where('stock_unit_id', $unit->id)->count());
        $line = $service->details($this->actor, $created['transfer_id'])['lines'][0];
        $received = $service->receive($this->actor, $destination, $created['transfer_id'], 'serial-receive', [
            'transfer_version' => $dispatch['version'],
            'lines' => [['line_id' => $line['line_id'], 'receive_unit_ids' => [$unit->public_id], 'reject_unit_ids' => []]],
        ]);
        $this->assertSame('received', $received['status']);
        $successorId = DB::table('stock_unit_lineage')->where('source_unit_id', $unit->id)->value('successor_unit_id');
        $successor = StockUnit::findOrFail($successorId);
        $this->assertSame('transferred_out', $unit->fresh()->status);
        $this->assertSame($target->id, $successor->product_id);
        $this->assertSame('in_stock', $successor->status);
        $this->assertSame(0, $source->fresh()->qty);
        $this->assertSame(1, $target->fresh()->qty);
        $this->assertSame(2, DB::table('product_imeis')->where('stock_unit_id', $unit->id)->where('status', 'transferred_out')->count());
        $this->assertSame(2, DB::table('product_imeis')->where('stock_unit_id', $successor->id)->where('status', 'in_stock')->count());
        $this->assertSame(2, DB::table('active_imeis')->where('stock_unit_id', $successor->id)->count());
        $this->assertSame(0, DB::table('active_imeis')->where('stock_unit_id', $unit->id)->count());
    }

    public function test_serialized_rejection_releases_hold_without_changing_stock_or_imei_owner(): void
    {
        $source = $this->product(true);
        [$destination, $target] = $this->destination($source);
        $this->acquire($source);
        $unit = StockUnit::where('product_id', $source->id)->firstOrFail();
        $this->imeis($source, $unit, [1 => '356900000000011', 2 => '356900000000012']);
        $service = app(StockTransferOperations::class);
        $created = $service->create($this->actor, $this->outlet, 'reject-create', [
            'destination_outlet_id' => $destination->public_id,
            'lines' => [['source_product_id' => $source->public_id, 'destination_product_id' => $target->public_id, 'unit_ids' => [$unit->public_id]]],
        ]);
        $dispatch = $service->dispatch($this->actor, $this->outlet, $created['transfer_id'], 'reject-dispatch', ['transfer_version' => 1]);
        $line = $service->details($this->actor, $created['transfer_id'])['lines'][0];
        $result = $service->receive($this->actor, $destination, $created['transfer_id'], 'reject-receive', [
            'transfer_version' => $dispatch['version'],
            'lines' => [['line_id' => $line['line_id'], 'receive_unit_ids' => [], 'reject_unit_ids' => [$unit->public_id]]],
        ]);
        $this->assertSame('rejected', $result['status']);
        $this->assertSame(1, $source->fresh()->qty);
        $this->assertSame(0, $target->fresh()->qty);
        $this->assertSame('in_stock', $unit->fresh()->status);
        $this->assertSame(2, DB::table('active_imeis')->where('stock_unit_id', $unit->id)->count());
        $this->assertSame(0, DB::table('stock_movements')->whereIn('type', ['transfer_in', 'transfer_out'])->count());
        $this->assertSame(0, DB::table('inventory_custody_holds')->whereNull('released_at')->count());
    }

    public function test_stale_destination_cannot_create_transfer_after_archival(): void
    {
        $source = $this->product();
        [$destination, $target] = $this->destination($source);
        $this->acquire($source, 2);
        $staleDestinationId = $destination->public_id;
        // Synthetic archived state exercises the locked recheck without authorizing a real archive.
        $destination->forceFill(['archived_at' => now()])->save();
        try {
            app(StockTransferOperations::class)->create($this->actor, $this->outlet, 'stale-destination-d03', [
                'destination_outlet_id' => $staleDestinationId,
                'lines' => [['source_product_id' => $source->public_id,
                    'destination_product_id' => $target->public_id, 'quantity' => 1]],
            ]);
            $this->fail('Archived destination unexpectedly accepted stock transfer creation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('stock_transfers')->where('destination_outlet_id', $destination->id)->count());
        $this->assertSame(2, (int) $source->fresh()->qty);
    }

    public function test_archived_transfer_participants_block_dispatch_receive_and_replays_without_changing_custody(): void
    {
        $source = $this->product();
        [$destination, $target] = $this->destination($source);
        $this->acquire($source, 2);
        $service = app(StockTransferOperations::class);
        $createInput = ['destination_outlet_id' => $destination->public_id,
            'lines' => [['source_product_id' => $source->public_id,
                'destination_product_id' => $target->public_id, 'quantity' => 1]]];
        $created = $service->create($this->actor, $this->outlet, 'd03-transfer-create', $createInput);
        $transferId = $created['transfer_id'];
        $dispatchInput = ['transfer_version' => 1];
        // The actual archive service blocks transfer history; simulate an archived state only in this rolled-back fixture.
        $destination->forceFill(['archived_at' => now()])->save();
        foreach ([
            fn () => $service->create($this->actor, $this->outlet, 'd03-transfer-create', $createInput),
            fn () => $service->dispatch($this->actor, $this->outlet, $transferId, 'd03-denied-dispatch', $dispatchInput),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Archived transfer destination accepted a mutation or completed-key replay.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame('draft', DB::table('stock_transfers')->where('public_id', $transferId)->value('status'));
        $this->assertSame(0, DB::table('inventory_custody_holds')->whereNull('released_at')->count());
        $destination->forceFill(['archived_at' => null])->save();
        $dispatched = $service->dispatch($this->actor, $this->outlet, $transferId,
            'd03-valid-dispatch', $dispatchInput);
        $line = $service->details($this->actor, $transferId)['lines'][0];
        $receiveInput = ['transfer_version' => $dispatched['version'],
            'lines' => [['line_id' => $line['line_id'], 'receive_quantity' => 1, 'reject_quantity' => 0]]];
        $this->outlet->forceFill(['archived_at' => now()])->save();
        foreach ([
            fn () => $service->dispatch($this->actor, $this->outlet, $transferId,
                'd03-valid-dispatch', $dispatchInput),
            fn () => $service->receive($this->actor, $destination, $transferId,
                'd03-denied-receive-source', $receiveInput),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Archived transfer source accepted dispatch replay or destination-side receipt.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame(0, DB::table('stock_transfer_receipts')->count());
        $this->assertSame(2, (int) $source->fresh()->qty);
        $this->assertSame(0, (int) $target->fresh()->qty);
        $this->outlet->forceFill(['archived_at' => null])->save();
        $destination->forceFill(['archived_at' => now()])->save();
        try {
            $service->receive($this->actor, $destination, $transferId, 'd03-denied-receive-dest', $receiveInput);
            $this->fail('Archived receipt destination accepted transfer receipt.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $destination->forceFill(['archived_at' => null])->save();
        $received = $service->receive($this->actor, $destination, $transferId, 'd03-valid-receive', $receiveInput);
        $this->assertSame('received', $received['status']);
        $receipt = DB::table('stock_transfer_receipts')->firstOrFail();
        $this->assertSame(hash('sha256', $receipt->receipt_snapshot), $receipt->snapshot_sha256);
        $this->outlet->forceFill(['archived_at' => now()])->save();
        try {
            $service->receive($this->actor, $destination, $transferId, 'd03-valid-receive', $receiveInput);
            $this->fail('Archived source allowed a completed receipt replay.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('stock_transfer_receipts')->count());
        $this->assertSame(1, (int) $source->fresh()->qty);
        $this->assertSame(1, (int) $target->fresh()->qty);
    }

    public function test_transfer_idempotency_and_receive_permission_are_enforced(): void
    {
        $source = $this->product();
        [$destination, $target] = $this->destination($source);
        $this->acquire($source, 2);
        $service = app(StockTransferOperations::class);
        $input = [
            'destination_outlet_id' => $destination->public_id,
            'lines' => [['source_product_id' => $source->public_id, 'destination_product_id' => $target->public_id, 'quantity' => 1]],
        ];
        $created = $service->create($this->actor, $this->outlet, 'idempotent-create', $input);
        $this->assertEquals($created, $service->create($this->actor, $this->outlet, 'idempotent-create', $input));
        $this->reject(fn () => $service->create($this->actor, $this->outlet, 'idempotent-create', [...$input, 'notes' => 'changed replay']));
        $dispatch = $service->dispatch($this->actor, $this->outlet, $created['transfer_id'], 'idempotent-dispatch', ['transfer_version' => 1]);
        $limited = new Admin;
        $limited->forceFill([
            'name' => 'Dispatch only',
            'email' => 'dispatch-only@example.invalid',
            'password' => 'SyntheticPass123!',
            'permissions' => ['shops.enter', 'shop.transfers.dispatch'],
        ])->save();
        $limited->shops()->attach([$this->outlet->id, $destination->id]);
        $line = $service->details($this->actor, $created['transfer_id'])['lines'][0];
        $this->reject(fn () => $service->receive($limited, $destination, $created['transfer_id'], 'forbidden-receive', [
            'transfer_version' => $dispatch['version'],
            'lines' => [['line_id' => $line['line_id'], 'receive_quantity' => 1, 'reject_quantity' => 0]],
        ]));
        $this->assertSame('in_transit', DB::table('stock_transfers')->where('public_id', $created['transfer_id'])->value('status'));
        $this->assertSame(1, DB::table('inventory_custody_holds')->whereNull('released_at')->count());
    }

    private function destination(Product $source): array
    {
        $outlet = new Outlet;
        $outlet->forceFill([
            'public_id' => (string) Str::uuid(),
            'name' => 'Synthetic destination',
            'outlet_code' => '025',
        ])->save();
        $this->actor->shops()->attach($outlet);
        $input = [
            'name' => $source->name,
            'category' => $source->category,
            'brand_master_data_id' => $source->brand_master_data_id,
            'subcategory_master_data_id' => $source->subcategory_master_data_id,
            'model' => $source->model,
            'purchase_price' => $source->purchase_price,
            'sale_price' => $source->sale_price,
            'track_imei' => (bool) $source->track_imei,
            'ram_master_data_id' => $source->ram_master_data_id,
            'storage_master_data_id' => $source->storage_master_data_id,
            'sim_master_data_id' => $source->sim_master_data_id,
            'warranty_type' => 'no_warranty',
        ];
        $product = app(ProductDefinitions::class)->save($this->actor, $outlet, $input);

        return [$outlet, $product];
    }
}
