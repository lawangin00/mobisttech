<?php

namespace Tests\Feature;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Addendum\ResetRetention;
use App\Addendum\WebsiteCapabilities;
use App\Catalog\ProductDefinitions;
use App\Identity\Access;
use App\Inventory\InventoryOperations;
use App\Inventory\StockLedger;
use App\Inventory\TransactionalStock;
use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\Outlet;
use App\Models\StockUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class AddendumFoundationsTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    public function test_unpublished_mode_and_unknown_or_history_operations_never_authorize_creation(): void
    {
        $capabilities = app(WebsiteCapabilities::class);
        $this->assertSame(['commerce' => false, 'digital' => false], $capabilities->snapshot()['capabilities']);
        $contract = json_decode(file_get_contents(base_path('../docs/addendum/CONTRACTS.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($contract['examples']['unpublished'], $capabilities->snapshot());
        foreach (['checkout.create', 'enquiry.create', 'unknown', 'approved-milestone.pay', 'invoice.read'] as $operation) {
            $this->reject(fn () => $capabilities->assertCreationAllowed($operation));
        }
    }

    public function test_three_modes_are_versioned_and_only_allow_their_declared_public_capabilities(): void
    {
        $quote = $this->quote();
        foreach (['digital_only', 'hybrid', 'commerce_only'] as $index => $mode) {
            $revision = DB::table('site_configuration_revisions')->insertGetId(['domain' => 'website.mode', 'version' => $index + 1,
                'state' => 'published', 'snapshot' => json_encode(['mode' => $mode]), 'published_at' => now()]);
            DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], ['mode' => $mode, 'version' => $index + 1, 'revision_id' => $revision, 'published_at' => now()]);
            $snapshot = app(WebsiteCapabilities::class)->snapshot();
            $this->assertSame((string) ($index + 1), $snapshot['version']);
            $this->assertSame('website-mode:'.($index + 1).':'.$mode, $snapshot['cache_namespace']);
            foreach (WebsiteCapabilities::PUBLIC_OPERATIONS as $operation => $capability) {
                $enabled = $capability === 'commerce' ? $mode !== 'digital_only' : $mode !== 'commerce_only';
                if ($enabled) {
                    $this->assertSame($mode, app(WebsiteCapabilities::class)->assertCreationAllowed($operation)['mode']);
                } else {
                    $this->reject(fn () => app(WebsiteCapabilities::class)->assertCreationAllowed($operation));
                }
            }
            $this->reject(fn () => app(WebsiteCapabilities::class)->assertCreationAllowed('project.read'));
            $this->assertSame('100.00', DB::table('project_quotes')->where('id', $quote)->value('amount'));
        }
        DB::table('website_operating_profiles')->where('id', 1)->update(['version' => 99]);
        $this->reject(fn () => app(WebsiteCapabilities::class)->snapshot());
    }

    public function test_profile_constraints_reject_second_singleton_invalid_revision_and_zero_version(): void
    {
        $revision = DB::table('site_configuration_revisions')->insertGetId(['domain' => 'website.mode', 'version' => 1, 'state' => 'draft', 'snapshot' => '{}']);
        $row = ['id' => 1, 'mode' => 'hybrid', 'version' => 1, 'revision_id' => $revision, 'published_at' => now()];
        $this->reject(fn () => DB::table('website_operating_profiles')->insert([...$row, 'id' => 2]));
        $this->reject(fn () => DB::table('website_operating_profiles')->insert([...$row, 'version' => 0]));
        $this->reject(fn () => DB::table('website_operating_profiles')->insert([...$row, 'revision_id' => 99999999]));
        DB::table('website_operating_profiles')->insert($row);
        $this->reject(fn () => app(WebsiteCapabilities::class)->snapshot());
    }

    public function test_new_permissions_preserve_defaults_and_realm_outlet_and_publish_boundaries(): void
    {
        $this->inventoryFixture();
        $admin = new Admin;
        $admin->forceFill(['name' => 'Synthetic', 'email' => 'addendum@example.invalid', 'password' => 'SyntheticPass123!'])->save();
        $admin->shops()->attach($this->outlet);
        $access = app(Access::class);
        $this->assertCount(9, Admin::defaultPermissions());
        $this->assertTrue($access->allows($admin, 'shop.inventory', $this->outlet));
        $this->assertFalse($access->allows($admin, 'shop.stocktake', $this->outlet));
        $admin->forceFill(['permissions' => ['shops.enter', 'shop.stocktake', 'system.reset.factory']])->save();
        $this->assertTrue($access->allows($admin, 'shop.stocktake', $this->outlet));
        $this->assertFalse($access->allows($admin, 'shop.stocktake'));
        $other = new Outlet;
        $other->forceFill(['name' => 'Other', 'outlet_code' => '028', 'public_id' => (string) Str::uuid()])->save();
        $this->assertFalse($access->allows($admin, 'shop.stocktake', $other));
        $this->assertTrue($access->allows($admin, 'system.reset.factory'));
        $this->assertTrue($access->allows($this->actor, 'system.reset.preview'));
        $this->assertFalse($access->allows($this->actor, 'system.reset.preview', $this->outlet));
        $this->assertTrue($access->allows($this->actor, 'website.mode.publish'));
        $cms = new User;
        $cms->forceFill(['is_admin' => true, 'admin_role' => 'content_editor']);
        $this->assertFalse($access->allows($cms, 'website.mode.preview'));
        $this->assertFalse($access->allows($cms, 'website.mode.publish'));
        $cms->admin_role = 'owner';
        $this->assertFalse($access->allows($cms, 'website.mode.publish'));
        $this->assertFalse($access->allows($cms, 'system.reset.factory'));
        $customer = new CustomerAccount;
        $customer->forceFill(['is_admin' => false]);
        $this->assertFalse($access->allows($customer, 'website.mode.preview'));
    }

    public function test_quantity_custody_holds_block_sale_reservation_and_excess_adjustment_without_moving_history(): void
    {
        $this->inventoryFixture();
        $product = $this->product();
        $destination = $this->product();
        $this->acquire($product, 3);
        $hold = DB::table('inventory_custody_holds')->insertGetId(['operation_id' => (string) Str::uuid(), 'product_id' => $product->id,
            'destination_product_id' => $destination->id, 'quantity' => 2, 'created_at' => now()]);
        $stock = app(StockLedger::class);
        $this->assertSame(1, $stock->snapshot($product->id)['available']);
        $sale = $this->sale($product, 2);
        $this->reject(fn () => app(TransactionalStock::class)->consumeSale($sale));
        $reservation = $this->reservation($product, 2);
        $this->reject(fn () => app(TransactionalStock::class)->reserve($reservation['line']));
        $this->reject(fn () => app(InventoryOperations::class)->adjust($this->actor, $this->outlet, $product->public_id,
            (string) Str::uuid(), ['type' => 'correction_out', 'quantity' => 2, 'reason' => 'Synthetic held stock correction']));
        $this->assertSame(3, $product->fresh()->qty);
        $this->assertSame(0, $product->fresh()->sold_qty);
        DB::table('inventory_custody_holds')->where('id', $hold)->update(['released_at' => now()]);
        $this->assertSame(3, $stock->snapshot($product->id)['available']);
    }

    public function test_serialized_custody_preserves_active_imei_claims_and_rejects_duplicate_or_cross_product_units(): void
    {
        $this->inventoryFixture();
        $product = $this->product(true);
        $destination = $this->product(true);
        $this->acquire($product);
        $this->imeis($product);
        $unit = StockUnit::where('product_id', $product->id)->firstOrFail();
        $code = $unit->unit_code;
        $row = ['operation_id' => (string) Str::uuid(), 'product_id' => $product->id, 'destination_product_id' => $destination->id,
            'stock_unit_id' => $unit->id, 'quantity' => 1, 'created_at' => now()];
        DB::table('inventory_custody_holds')->insert($row);
        $this->reject(fn () => DB::table('inventory_custody_holds')->insert($row));
        $this->reject(fn () => DB::table('inventory_custody_holds')->insert([...$row, 'quantity' => 2]));
        $this->reject(fn () => DB::table('inventory_custody_holds')->insert([...$row, 'product_id' => $destination->id]));
        $this->assertSame(0, app(StockLedger::class)->snapshot($product->id)['available']);
        $this->reject(fn () => $this->imeis($product, $unit, [1 => 'changed1', 2 => 'changed2']));
        $this->assertSame(2, DB::table('active_imeis')->where('stock_unit_id', $unit->id)->count());
        $this->assertSame($code, $unit->fresh()->unit_code);
        $this->assertSame($product->id, $unit->fresh()->product_id);
        $this->reject(fn () => app(ProductDefinitions::class)->unitAttributes($this->actor, $this->outlet, $unit->public_id, []));
    }

    public function test_lineage_rejects_cycles_branching_and_deletion_of_origin_history(): void
    {
        $this->inventoryFixture();
        $product = $this->product(true);
        $this->acquire($product, 3);
        $ids = StockUnit::where('product_id', $product->id)->orderBy('id')->pluck('id')->all();
        $row = ['source_unit_id' => $ids[0], 'successor_unit_id' => $ids[1], 'reason' => 'transfer', 'operation_id' => (string) Str::uuid(), 'created_at' => now()];
        DB::table('stock_unit_lineage')->insert($row);
        $this->reject(fn () => DB::table('stock_unit_lineage')->insert([...$row, 'successor_unit_id' => $ids[2]]));
        $this->reject(fn () => DB::table('stock_unit_lineage')->insert([...$row, 'source_unit_id' => $ids[1], 'successor_unit_id' => $ids[0]]));
        $this->reject(fn () => DB::table('stock_units')->where('id', $ids[0])->delete());
        $this->assertSame(3, StockUnit::where('product_id', $product->id)->count());
    }

    public function test_adjustment_identity_is_exact_append_only_replayable_and_not_a_second_money_engine(): void
    {
        $this->inventoryFixture();
        $invoice = DB::table('invoices')->insertGetId(['outlet_id' => $this->outlet->id, 'total_bill' => '100.00', 'final_bill' => '100.00', 'public_id' => (string) Str::uuid()]);
        $snapshot = MoneySnapshot::adjustment((string) Str::uuid(), 'trade_in_credit', '25.01', 'Approved synthetic valuation', (string) Str::uuid());
        $service = app(FinancialReferences::class);
        $id = $service->adjustment('invoice', $invoice, $snapshot);
        $this->assertSame($id, $service->adjustment('invoice', $invoice, array_reverse($snapshot, true)));
        $this->reject(fn () => $service->adjustment('invoice', $invoice, [...$snapshot, 'amount' => '25.02']));
        $this->reject(fn () => $service->adjustment('invoice', $invoice, MoneySnapshot::adjustment((string) Str::uuid(), 'trade_in_credit',
            '25.01', 'Duplicate synthetic valuation', $snapshot['source_reference'])));
        $this->assertSame(1, DB::table('monetary_adjustments')->count());
        $this->assertSame('tender', DB::table('monetary_adjustments')->where('id', $id)->value('treatment'));
        $this->assertSame('100.00', DB::table('invoices')->where('id', $invoice)->value('final_bill'));
        $row = (array) DB::table('monetary_adjustments')->where('id', $id)->first();
        $this->reject(fn () => DB::table('monetary_adjustments')->insert([...$row, 'id' => (string) Str::uuid(), 'treatment' => 'discount']));
        $this->reject(fn () => DB::table('monetary_adjustments')->insert([...$row, 'id' => (string) Str::uuid(), 'invoice_id' => null]));
        $this->reject(fn () => DB::table('invoices')->where('id', $invoice)->delete());
    }

    public function test_milestones_cannot_exceed_approved_amount_or_change_paid_history(): void
    {
        $quote = $this->quote();
        $publicId = DB::table('project_quotes')->where('id', $quote)->value('public_id');
        $snapshot = MoneySnapshot::milestone((string) Str::uuid(), $publicId, 1, '60.01', str_repeat('a', 64));
        $service = app(FinancialReferences::class);
        $id = $service->milestone($quote, $snapshot);
        $this->reject(fn () => $service->milestone($quote, MoneySnapshot::milestone((string) Str::uuid(), $publicId, 2, '40.00', str_repeat('a', 64))));
        DB::table('project_quotes')->where('id', $quote)->update(['status' => 'paid', 'paid_at' => now()]);
        $this->assertSame($id, $service->milestone($quote, $snapshot));
        $this->reject(fn () => $service->milestone($quote, [...$snapshot, 'approved_amount' => '59.99']));
        $this->reject(fn () => $service->milestone($quote, MoneySnapshot::milestone((string) Str::uuid(), $publicId, 2, '1.00', str_repeat('a', 64))));
        $this->assertSame('60.01', DB::table('project_milestone_identities')->where('id', $id)->value('approved_amount'));
        $this->reject(fn () => DB::table('project_quotes')->where('id', $quote)->delete());
    }

    public function test_reference_writes_rollback_with_the_owning_transaction(): void
    {
        $quote = $this->quote();
        $publicId = DB::table('project_quotes')->where('id', $quote)->value('public_id');
        try {
            DB::transaction(function () use ($quote, $publicId) {
                app(FinancialReferences::class)->milestone($quote, MoneySnapshot::milestone((string) Str::uuid(), $publicId, 1, '25.00', str_repeat('b', 64)));
                throw new \LogicException('Synthetic outer rollback');
            });
        } catch (\LogicException $e) {
            $this->assertSame('Synthetic outer rollback', $e->getMessage());
        }
        $this->assertSame(0, DB::table('project_milestone_identities')->count());
        $this->assertSame('100.00', DB::table('project_quotes')->where('id', $quote)->value('amount'));
    }

    public function test_procurement_reference_retains_acquisition_evidence_and_requires_a_real_acquisition(): void
    {
        $this->inventoryFixture();
        $product = $this->product();
        $acquisition = $this->acquire($product)['acquisition_id'];
        $original = (array) DB::table('stock_acquisitions')->where('id', $acquisition)->first();
        $row = ['id' => (string) Str::uuid(), 'acquisition_id' => $acquisition, 'kind' => 'purchase_order_receipt',
            'source_line_id' => (string) Str::uuid(), 'created_at' => now()];
        DB::table('acquisition_source_references')->insert($row);
        $this->reject(fn () => DB::table('acquisition_source_references')->insert([...$row, 'id' => (string) Str::uuid()]));
        $this->reject(fn () => DB::table('acquisition_source_references')->insert([...$row, 'id' => (string) Str::uuid(), 'acquisition_id' => 99999999]));
        $this->reject(fn () => DB::table('stock_acquisitions')->where('id', $acquisition)->delete());
        $this->assertSame($original, (array) DB::table('stock_acquisitions')->where('id', $acquisition)->first());
    }

    public function test_milestone_order_links_require_existing_items_and_retain_referenced_history(): void
    {
        $quote = $this->quote();
        $quotePublic = DB::table('project_quotes')->where('id', $quote)->value('public_id');
        $milestone = app(FinancialReferences::class)->milestone($quote, MoneySnapshot::milestone((string) Str::uuid(), $quotePublic, 1, '100.00', str_repeat('c', 64)));
        $order = DB::table('orders')->insertGetId(['order_number' => 'MT28-'.Str::uuid(), 'order_type' => 'digital', 'customer_name' => 'Synthetic',
            'customer_mobile' => '03000000000', 'public_id' => (string) Str::uuid()]);
        $item = DB::table('order_items')->insertGetId(['order_id' => $order, 'item_type' => 'digital', 'title' => 'Synthetic milestone',
            'project_quote_id' => $quote, 'quantity' => 1, 'unit_price' => '100.00', 'line_total' => '100.00']);
        DB::table('order_item_milestones')->insert(['order_item_id' => $item, 'milestone_id' => $milestone]);
        $other = DB::table('order_items')->insertGetId(['order_id' => $order, 'item_type' => 'digital', 'title' => 'Duplicate synthetic milestone',
            'project_quote_id' => $quote, 'quantity' => 1, 'unit_price' => '100.00', 'line_total' => '100.00']);
        $this->reject(fn () => DB::table('order_item_milestones')->insert(['order_item_id' => $other, 'milestone_id' => $milestone]));
        $this->reject(fn () => DB::table('order_item_milestones')->insert(['order_item_id' => 99999999, 'milestone_id' => $milestone]));
        $this->reject(fn () => DB::table('project_milestone_identities')->where('id', $milestone)->delete());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertNull(DB::table('project_quotes')->where('id', $quote)->value('paid_at'));
    }

    public function test_reset_classifies_every_table_and_blocks_retained_financial_dependencies_without_deletion(): void
    {
        $quote = $this->quote();
        $service = app(ResetRetention::class);
        $names = array_column(Schema::getTables(schema: DB::connection()->getDatabaseName()), 'name');
        $this->assertCount(105, $names);
        foreach (ResetRetention::LEVELS as $level) {
            $plan = $service->classify($level, $names);
            $this->assertCount(105, $plan['tables']);
            $this->assertFalse($plan['executable']);
            $this->assertSame('preserve', $plan['tables']['backup_records']['action']);
        }
        $this->assertSame('preserve', $service->classify('transactional', $names)['tables']['stock_units']['action']);
        $this->assertSame('row_selection_required', $service->classify('business', $names)['tables']['users']['action']);
        $this->assertSame('bootstrap_review', $service->classify('factory', $names)['tables']['admins']['action']);
        $barriers = $service->dependencyBarriers('transactional')['barriers'];
        $this->assertTrue(collect($barriers)->contains(fn ($b) => $b['retained_child'] === 'stock_units' && $b['cleared_parent'] === 'invoices'));
        $this->reject(fn () => $service->classify('business', [...$names, 'new_unknown_domain']));
        $this->reject(fn () => $service->classify('anything', $names));
        $this->assertTrue(DB::table('project_quotes')->where('id', $quote)->exists());
    }

    private function quote(): int
    {
        return DB::table('project_quotes')->insertGetId(['reference' => 'MT28-'.Str::uuid(), 'client_name' => 'Synthetic',
            'customer_mobile' => '03000000000', 'title' => 'Synthetic project', 'amount' => '100.00', 'public_id' => (string) Str::uuid()]);
    }
}
