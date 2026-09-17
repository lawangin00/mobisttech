<?php

// Inspect only the disposable target test schema. No DDL, row data or credentials are emitted.
$root = dirname(__DIR__, 2);
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'FAIL: '.$error->getMessage().PHP_EOL);
    exit(1);
});
$db = Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@session.time_zone AS timezone, @@session.sql_mode AS sql_mode, VERSION() AS version');
if ($db->db !== 'mobisttech_test' || (int) $db->port !== 13306 || ! $app->environment('testing')) {
    throw new RuntimeException('Exact disposable target test schema required.');
}
$mode = $argv[1] ?? '';
if (! in_array($mode, ['promotion', 'trade-in', 'cash', 'payments', 'transfer', 'stocktake', 'procurement', 'team-members', 'orders', 'admin-google', 'warranty', 'sales', 'addendum', 'identity', 'shared', 'runtime'], true)) {
    throw new RuntimeException('Expected promotion, trade-in, cash, payments, transfer, stocktake, procurement, team-members, orders, admin-google, warranty, sales, addendum, identity, shared or runtime verification mode.');
}
$paymentPermissions = ['config.payments.manage', 'shop.payments.reconcile', 'shop.payments.refund-override', 'shop.payments.refund-approve'];
$checkpointPermissions = array_keys(App\Models\Admin::PERMISSIONS);
if (! in_array($mode, ['payments', 'cash', 'trade-in', 'promotion'], true)) {
    $checkpointPermissions = array_values(array_diff($checkpointPermissions, $paymentPermissions));
}
$schema = Illuminate\Support\Facades\Schema::getFacadeRoot();
$spec = json_decode(file_get_contents($root.'/docs/schema/TARGET_SCHEMA.json'), true, flags: JSON_THROW_ON_ERROR);
$runtime = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'migrations'];
$expected = $mode !== 'runtime' ? array_merge($runtime, array_keys($spec['tables'])) : $runtime;
if (in_array($mode, ['identity', 'addendum', 'sales', 'warranty', 'admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['admin_password_reset_tokens', 'super_admin_password_reset_tokens', 'site_admin_password_reset_tokens', 'identity_audit_events']);
}
if (in_array($mode, ['addendum', 'sales', 'warranty', 'admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['website_operating_profiles', 'stock_unit_lineage', 'inventory_custody_holds', 'acquisition_source_references', 'monetary_adjustments', 'project_milestone_identities', 'order_item_milestones']);
}
if (in_array($mode, ['sales', 'warranty', 'admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    foreach (['public_id', 'version', 'returned_quantity'] as $column) {
        if (! $schema->hasColumn('sales', $column)) {
            throw new RuntimeException('Missing sales integrity column: '.$column);
        }
    }
    foreach (['public_id', 'invoice_id', 'successor_stock_unit_id', 'unit_price', 'discount_amount', 'net_amount', 'purchase_amount', 'currency', 'sale_snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('return_lines', $column)) {
            throw new RuntimeException('Missing return integrity column: '.$column);
        }
    }
}
if (in_array($mode, ['warranty', 'admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected[] = 'claim_events';
    foreach (['warranty_snapshot', 'warranty_expires_at', 'active_stock_unit_id'] as $column) {
        if (! $schema->hasColumn('claims', $column)) {
            throw new RuntimeException('Missing warranty claim integrity column: '.$column);
        }
    }
}
if (in_array($mode, ['admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['business_profiles', 'admin_identity_mappings', 'integration_connections', 'integration_oauth_states', 'integration_events']);
    foreach (['provider', 'status', 'encrypted_credentials', 'authorized_by_admin_id', 'last_success_at'] as $column) {
        if (! $schema->hasColumn('integration_connections', $column)) {
            throw new RuntimeException('Missing secure integration column: '.$column);
        }
    }
    if (! $schema->hasColumn('backup_records', 'integration_connection_id')) {
        throw new RuntimeException('Missing Google Drive backup integration reference.');
    }
}
if (in_array($mode, ['orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    foreach (['owner_scope_hash'] as $column) {
        if (! $schema->hasColumn('orders', $column)) {
            throw new RuntimeException('Missing order ownership column: '.$column);
        }
    }
    foreach (['intent_hash', 'failure_code', 'reconciliation_required_at', 'completed_at'] as $column) {
        if (! $schema->hasColumn('payments', $column)) {
            throw new RuntimeException('Missing payment integrity column: '.$column);
        }
    }
    if (! $schema->hasColumn('payment_receipts', 'outcome') || ! $schema->hasColumn('project_milestone_identities', 'paid_payment_id')) {
        throw new RuntimeException('Missing receipt or milestone payment linkage.');
    }
}
if (in_array($mode, ['team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['permission_definitions', 'roles', 'role_permissions', 'admin_roles', 'team_member_audit_events']);
    foreach (['job_title', 'auth_version'] as $column) {
        if (! $schema->hasColumn('admins', $column)) {
            throw new RuntimeException('Missing Team Member account column: '.$column);
        }
    }
    if (! $schema->hasColumn('account_sessions', 'last_human_activity')
        || ! $schema->hasColumn('identity_audit_events', 'actor_role_snapshot')) {
        throw new RuntimeException('Missing realm session or historical actor snapshot column.');
    }
    $expectedRoles = ['Full Access', 'Manager', 'Store Manager', 'Sales Associate', 'Cashier', 'Inventory Manager',
        'Service & Warranty', 'Online Store Editor', 'Merchandiser', 'Customer Support', 'Digital Operations', 'Custom Role'];
    $actualRoles = Illuminate\Support\Facades\DB::table('roles')->orderBy('id')->pluck('name')->all();
    if ($actualRoles !== $expectedRoles || ! Illuminate\Support\Facades\DB::table('roles')->where('name', 'Full Access')->where('is_protected', true)->exists()) {
        throw new RuntimeException('Default Team Member role catalogue differs from the approved policy.');
    }
    $permissionCodes = Illuminate\Support\Facades\DB::table('permission_definitions')->pluck('code')->sort()->values()->all();
    $modelCodes = $checkpointPermissions;
    sort($modelCodes);
    if ($permissionCodes !== $modelCodes) {
        throw new RuntimeException('Permission definition catalogue differs from the server authorization catalogue.');
    }
}
if (in_array($mode, ['procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['suppliers', 'supplier_contacts', 'purchase_orders', 'purchase_order_lines',
        'purchase_order_receipts', 'purchase_order_receipt_lines', 'purchase_order_events', 'reorder_policies']);
    foreach (['ordered_quantity', 'received_quantity', 'ordered_unit_cost', 'planned_landed_unit_cost'] as $column) {
        if (! $schema->hasColumn('purchase_order_lines', $column)) {
            throw new RuntimeException('Missing purchase-order line integrity column: '.$column);
        }
    }
    foreach (['acquisition_id', 'quantity', 'received_unit_cost', 'received_landed_unit_cost'] as $column) {
        if (! $schema->hasColumn('purchase_order_receipt_lines', $column)) {
            throw new RuntimeException('Missing receipt-to-acquisition integrity column: '.$column);
        }
    }
    if (! $schema->hasColumn('reorder_policies', 'reorder_threshold') || ! $schema->hasColumn('purchase_order_events', 'snapshot_sha256')) {
        throw new RuntimeException('Missing reorder or procurement audit contract.');
    }
}
if (in_array($mode, ['stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['stocktake_sessions', 'stocktake_lines', 'stocktake_unit_baselines', 'stocktake_counts', 'stocktake_recounts', 'stocktake_approvals']);
    foreach (['baseline_quantity', 'baseline_movement_id', 'baseline_at', 'current_iteration', 'expected_quantity_at_count', 'variance'] as $column) {
        if (! $schema->hasColumn('stocktake_lines', $column)) {
            throw new RuntimeException('Missing stocktake line integrity column: '.$column);
        }
    }
    foreach (['expected_quantity', 'counted_quantity', 'variance', 'count_snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('stocktake_counts', $column)) {
            throw new RuntimeException('Missing immutable stocktake count column: '.$column);
        }
    }
    if (! $schema->hasColumn('stocktake_unit_baselines', 'stock_unit_public_id_snapshot')
        || ! $schema->hasColumn('stocktake_recounts', 'prior_count_snapshot')
        || ! $schema->hasColumn('stocktake_approvals', 'approval_snapshot')) {
        throw new RuntimeException('Missing serialized baseline, recount or approval audit contract.');
    }
}
if (in_array($mode, ['transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['stock_transfers', 'stock_transfer_lines', 'stock_transfer_units',
        'stock_transfer_receipts', 'stock_transfer_receipt_lines']);
    foreach (['public_id', 'transfer_number', 'source_outlet_id', 'destination_outlet_id', 'status', 'version', 'dispatched_at', 'completed_at'] as $column) {
        if (! $schema->hasColumn('stock_transfers', $column)) {
            throw new RuntimeException('Missing stock-transfer header integrity column: '.$column);
        }
    }
    foreach (['source_product_id', 'destination_product_id', 'quantity', 'received_quantity', 'rejected_quantity', 'product_snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('stock_transfer_lines', $column)) {
            throw new RuntimeException('Missing stock-transfer line integrity column: '.$column);
        }
    }
    foreach (['source_stock_unit_id', 'source_stock_unit_public_id_snapshot', 'successor_stock_unit_id', 'status', 'source_unit_snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('stock_transfer_units', $column)) {
            throw new RuntimeException('Missing serialized stock-transfer custody column: '.$column);
        }
    }
    if (! $schema->hasColumn('stock_transfer_receipts', 'receipt_snapshot')
        || ! $schema->hasColumn('stock_transfer_receipt_lines', 'line_snapshot')) {
        throw new RuntimeException('Missing immutable stock-transfer receipt audit contract.');
    }
    $imeiStatus = (array) Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM product_imeis WHERE Field = 'status'");
    if (! str_contains((string) ($imeiStatus['Type'] ?? ''), "'transferred_out'")) {
        throw new RuntimeException('Transferred-out IMEI history state is missing.');
    }
}
if (in_array($mode, ['payments', 'cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['pos_payment_destinations', 'pos_tender_allocations', 'pos_settlement_events', 'pos_refund_allocations']);
    foreach (['public_id', 'outlet_id', 'method', 'display_name', 'masked_identifier', 'active', 'effective_from', 'effective_until', 'requires_refund_override_approval', 'version'] as $column) {
        if (! $schema->hasColumn('pos_payment_destinations', $column)) {
            throw new RuntimeException('Missing POS payment destination column: '.$column);
        }
    }
    foreach (['invoice_id', 'payment_destination_id', 'sequence', 'method', 'amount', 'cash_tendered', 'change_returned', 'destination_snapshot', 'snapshot_sha256', 'reconciliation_state', 'settlement_version'] as $column) {
        if (! $schema->hasColumn('pos_tender_allocations', $column)) {
            throw new RuntimeException('Missing POS tender allocation column: '.$column);
        }
    }
    foreach (['gross_amount', 'fee_amount', 'adjustment_amount', 'expected_net_amount', 'received_net_amount', 'variance_amount', 'event_snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('pos_settlement_events', $column)) {
            throw new RuntimeException('Missing POS settlement evidence column: '.$column);
        }
    }
    foreach (['return_id', 'original_tender_allocation_id', 'refund_destination_id', 'amount', 'original_method', 'refund_method', 'is_override', 'override_reason', 'approved_by_admin_id', 'original_tender_snapshot', 'refund_destination_snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('pos_refund_allocations', $column)) {
            throw new RuntimeException('Missing POS refund traceability column: '.$column);
        }
    }
    foreach ($paymentPermissions as $permission) {
        if (! Illuminate\Support\Facades\DB::table('permission_definitions')->where('code', $permission)->exists()) {
            throw new RuntimeException('Missing POS payment permission: '.$permission);
        }
    }
}
if (in_array($mode, ['cash', 'trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['cash_sessions', 'cash_entries']);
    foreach (['public_id', 'outlet_id', 'open_outlet_guard', 'opened_by_admin_id', 'closed_by_admin_id', 'variance_approved_by_admin_id',
        'business_date', 'status', 'version', 'opening_cash', 'expected_cash', 'actual_cash', 'variance_amount', 'variance_reason',
        'closing_snapshot', 'snapshot_sha256', 'opened_at', 'closed_at'] as $column) {
        if (! $schema->hasColumn('cash_sessions', $column)) {
            throw new RuntimeException('Missing cash-session integrity column: '.$column);
        }
    }
    foreach (['cash_session_id', 'outlet_id', 'type', 'amount', 'reason', 'status', 'created_by_admin_id', 'reviewed_by_admin_id', 'reviewed_at'] as $column) {
        if (! $schema->hasColumn('cash_entries', $column)) {
            throw new RuntimeException('Missing cash-entry integrity column: '.$column);
        }
    }
    if (! $schema->hasColumn('pos_tender_allocations', 'cash_session_id')
        || ! $schema->hasColumn('pos_refund_allocations', 'cash_session_id')) {
        throw new RuntimeException('Missing MT-2.20 to cash-session attribution columns.');
    }
    foreach (['shop.cash', 'shop.cash.approve'] as $permission) {
        if (! Illuminate\Support\Facades\DB::table('permission_definitions')->where('code', $permission)->exists()) {
            throw new RuntimeException('Missing cash-session permission: '.$permission);
        }
    }
}
if ($mode === 'promotion') {
    $expected = array_merge($expected, ['promotions', 'promotion_products', 'promotion_categories', 'promotion_claims', 'promotion_events']);
    foreach (['public_id', 'outlet_id', 'mode', 'code', 'discount_type', 'discount_value', 'max_discount', 'min_subtotal', 'usage_limit', 'per_customer_limit', 'customer_required', 'stackable', 'priority', 'status', 'version'] as $column) {
        if (! $schema->hasColumn('promotions', $column)) {
            throw new RuntimeException('Missing promotion configuration column: '.$column);
        }
    }
    foreach (['public_id', 'promotion_id', 'channel', 'owner_key', 'customer_key', 'invoice_id', 'order_id', 'discount_amount', 'status', 'snapshot', 'snapshot_sha256', 'released_at', 'release_reason'] as $column) {
        if (! $schema->hasColumn('promotion_claims', $column)) {
            throw new RuntimeException('Missing promotion claim integrity column: '.$column);
        }
    }
    foreach (['promotion_id', 'promotion_claim_id', 'event_type', 'snapshot', 'snapshot_sha256', 'created_at'] as $column) {
        if (! $schema->hasColumn('promotion_events', $column)) {
            throw new RuntimeException('Missing promotion audit column: '.$column);
        }
    }
    if (! Illuminate\Support\Facades\DB::table('permission_definitions')->where('code', 'config.promotions.manage')->exists()) {
        throw new RuntimeException('Missing promotion management permission.');
    }
}
if (in_array($mode, ['trade-in', 'promotion'], true)) {
    $expected = array_merge($expected, ['trade_ins', 'trade_in_identifiers', 'trade_in_events']);
    foreach (['public_id', 'outlet_id', 'product_id', 'invoice_id', 'acquisition_id', 'monetary_adjustment_id', 'seller_name',
        'seller_cnic', 'seller_phone', 'device_serial', 'device_condition', 'diagnostics', 'valuation_amount', 'settlement_mode',
        'status', 'version', 'approval_snapshot', 'approval_sha256', 'approved_at', 'received_at', 'cancelled_at'] as $column) {
        if (! $schema->hasColumn('trade_ins', $column)) {
            throw new RuntimeException('Missing trade-in integrity column: '.$column);
        }
    }
    foreach (['trade_in_id', 'slot_no', 'identifier', 'released_at', 'active_identifier'] as $column) {
        if (! $schema->hasColumn('trade_in_identifiers', $column)) {
            throw new RuntimeException('Missing trade-in identifier column: '.$column);
        }
    }
    if (! Illuminate\Support\Facades\DB::table('permission_definitions')->where('code', 'shop.trade-in')->exists()) {
        throw new RuntimeException('Missing trade-in permission.');
    }
}
$actual = array_column($schema->getTables(schema: $db->db), 'name');
sort($expected);
sort($actual);
if ($expected !== $actual) {
    throw new RuntimeException('MySQL table set differs from expected checkpoint: expected '.count($expected).', actual '.count($actual).'.');
}
$definitions = [];
$counts = ['tables' => count($actual), 'columns' => 0, 'foreign_keys' => 0, 'indexes' => 0];
foreach ($actual as $table) {
    $columns = $schema->getColumns($table);
    $counts['columns'] += count($columns);
    $counts['foreign_keys'] += count($schema->getForeignKeys($table));
    $counts['indexes'] += count($schema->getIndexes($table));
    $create = (array) Illuminate\Support\Facades\DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
    $definitions[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create['Create Table']);
    if (! str_contains($definitions[$table], 'ENGINE=InnoDB')) {
        throw new RuntimeException('Non-transactional table found.');
    }
    $seedRows = in_array($mode, ['admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true) ? ['business_profiles' => 1, 'integration_connections' => 2] : [];
    if (in_array($mode, ['team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)) {
        $seedRows += ['permission_definitions' => count($checkpointPermissions), 'roles' => 12,
            'role_permissions' => Illuminate\Support\Facades\DB::table('role_permissions')->count()];
    }
    $rowCount = Illuminate\Support\Facades\DB::table($table)->count();
    if (! in_array($table, $runtime, true) && $rowCount !== ($seedRows[$table] ?? 0)) {
        throw new RuntimeException('Unexpected business rows remain in disposable schema.');
    }
}
if (! str_contains($db->sql_mode, 'STRICT_TRANS_TABLES') || $db->timezone !== '+00:00') {
    throw new RuntimeException('Strict SQL and UTC session are required.');
}
echo json_encode(['mode' => $mode, 'result' => 'PASS', 'database' => $db->db, 'port' => (int) $db->port,
    'mysql' => $db->version, 'timezone' => $db->timezone, 'strict' => true, 'counts' => $counts,
    'schema_sha256' => hash('sha256', json_encode($definitions, JSON_UNESCAPED_SLASHES)),
    'business_rows' => 0, 'canonical_seed_rows' => in_array($mode, ['team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion'], true)
        ? 3 + count($checkpointPermissions) + 12 + Illuminate\Support\Facades\DB::table('role_permissions')->count()
        : (in_array($mode, ['admin-google', 'orders'], true) ? 3 : 0)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
