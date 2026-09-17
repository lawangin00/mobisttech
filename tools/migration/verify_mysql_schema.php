<?php

use App\Models\Admin;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Inspect only the disposable target test schema. No DDL, row data or credentials are emitted.
$root = dirname(__DIR__, 2);
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'FAIL: '.$error->getMessage().PHP_EOL);
    exit(1);
});
$db = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@session.time_zone AS timezone, @@session.sql_mode AS sql_mode, VERSION() AS version');
if ($db->db !== 'mobisttech_test' || (int) $db->port !== 13306 || ! $app->environment('testing')) {
    throw new RuntimeException('Exact disposable target test schema required.');
}
$mode = $argv[1] ?? '';
if (! in_array($mode, ['cms', 'digital-services', 'projects', 'engagement', 'operations', 'documents', 'repair', 'loyalty', 'promotion', 'trade-in', 'cash', 'payments', 'transfer', 'stocktake', 'procurement', 'team-members', 'orders', 'admin-google', 'warranty', 'sales', 'addendum', 'identity', 'shared', 'runtime'], true)) {
    throw new RuntimeException('Expected cms, digital-services, projects, engagement, operations, documents, repair, loyalty, promotion, trade-in, cash, payments, transfer, stocktake, procurement, team-members, orders, admin-google, warranty, sales, addendum, identity, shared or runtime verification mode.');
}
$paymentPermissions = ['config.payments.manage', 'shop.payments.reconcile', 'shop.payments.refund-override', 'shop.payments.refund-approve'];
$checkpointPermissions = array_keys(Admin::PERMISSIONS);
$documentPermissions = ['shop.documents.send'];
if (! in_array($mode, ['payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $checkpointPermissions = array_values(array_diff($checkpointPermissions, $paymentPermissions));
}
if (! in_array($mode, ['documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $checkpointPermissions = array_values(array_diff($checkpointPermissions, $documentPermissions));
}
$schema = Schema::getFacadeRoot();
$spec = json_decode(file_get_contents($root.'/docs/schema/TARGET_SCHEMA.json'), true, flags: JSON_THROW_ON_ERROR);
$runtime = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'migrations'];
$expected = $mode !== 'runtime' ? array_merge($runtime, array_keys($spec['tables'])) : $runtime;
if (in_array($mode, ['identity', 'addendum', 'sales', 'warranty', 'admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['admin_password_reset_tokens', 'super_admin_password_reset_tokens', 'site_admin_password_reset_tokens', 'identity_audit_events']);
}
if (in_array($mode, ['addendum', 'sales', 'warranty', 'admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['website_operating_profiles', 'stock_unit_lineage', 'inventory_custody_holds', 'acquisition_source_references', 'monetary_adjustments', 'project_milestone_identities', 'order_item_milestones']);
}
if (in_array($mode, ['sales', 'warranty', 'admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
if (in_array($mode, ['warranty', 'admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $expected[] = 'claim_events';
    foreach (['warranty_snapshot', 'warranty_expires_at', 'active_stock_unit_id'] as $column) {
        if (! $schema->hasColumn('claims', $column)) {
            throw new RuntimeException('Missing warranty claim integrity column: '.$column);
        }
    }
}
if (in_array($mode, ['admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
if (in_array($mode, ['orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
if (in_array($mode, ['team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
    $actualRoles = DB::table('roles')->orderBy('id')->pluck('name')->all();
    if ($actualRoles !== $expectedRoles || ! DB::table('roles')->where('name', 'Full Access')->where('is_protected', true)->exists()) {
        throw new RuntimeException('Default Team Member role catalogue differs from the approved policy.');
    }
    $permissionCodes = DB::table('permission_definitions')->pluck('code')->sort()->values()->all();
    $modelCodes = $checkpointPermissions;
    sort($modelCodes);
    if ($permissionCodes !== $modelCodes) {
        throw new RuntimeException('Permission definition catalogue differs from the server authorization catalogue.');
    }
}
if (in_array($mode, ['procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
if (in_array($mode, ['stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
if (in_array($mode, ['transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
    $imeiStatus = (array) DB::selectOne("SHOW COLUMNS FROM product_imeis WHERE Field = 'status'");
    if (! str_contains((string) ($imeiStatus['Type'] ?? ''), "'transferred_out'")) {
        throw new RuntimeException('Transferred-out IMEI history state is missing.');
    }
}
if (in_array($mode, ['payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
        if (! DB::table('permission_definitions')->where('code', $permission)->exists()) {
            throw new RuntimeException('Missing POS payment permission: '.$permission);
        }
    }
}
if (in_array($mode, ['cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
        if (! DB::table('permission_definitions')->where('code', $permission)->exists()) {
            throw new RuntimeException('Missing cash-session permission: '.$permission);
        }
    }
}
if (in_array($mode, ['promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
    if (! DB::table('permission_definitions')->where('code', 'config.promotions.manage')->exists()) {
        throw new RuntimeException('Missing promotion management permission.');
    }
}
if (in_array($mode, ['loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['loyalty_configurations', 'loyalty_accounts', 'loyalty_claims', 'loyalty_entries', 'loyalty_earn_lots', 'loyalty_claim_lots']);
    foreach (['public_id', 'version', 'enabled', 'earn_basis_amount', 'earn_points', 'redemption_value', 'min_redeem_points', 'max_redeem_points', 'daily_redeem_points', 'expiry_days', 'snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('loyalty_configurations', $column)) {
            throw new RuntimeException('Missing loyalty configuration column: '.$column);
        }
    }
    foreach (['public_id', 'monetary_adjustment_id', 'customer_id', 'account_id', 'channel', 'owner_key', 'invoice_id', 'order_id', 'points', 'discount_amount', 'status', 'snapshot', 'snapshot_sha256', 'released_at', 'release_reason'] as $column) {
        if (! $schema->hasColumn('loyalty_claims', $column)) {
            throw new RuntimeException('Missing loyalty claim integrity column: '.$column);
        }
    }
    foreach (['customer_id', 'account_id', 'loyalty_claim_id', 'invoice_id', 'order_id', 'return_id', 'entry_type', 'direction', 'points', 'event_key', 'snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('loyalty_entries', $column)) {
            throw new RuntimeException('Missing loyalty ledger column: '.$column);
        }
    }
    if (! $schema->hasColumn('loyalty_earn_lots', 'points_remaining') || ! $schema->hasColumn('loyalty_claim_lots', 'restored_points')) {
        throw new RuntimeException('Missing loyalty expiry or reversal allocation contract.');
    }
    if (! DB::table('permission_definitions')->where('code', 'config.loyalty.manage')->exists()) {
        throw new RuntimeException('Missing loyalty management permission.');
    }
}
if (in_array($mode, ['repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['repair_settings', 'repair_jobs', 'repair_estimates', 'repair_estimate_lines',
        'repair_estimate_approvals', 'repair_part_consumptions', 'repair_payment_links', 'repair_events']);
    foreach (['outlet_id', 'enabled', 'version', 'updated_by_admin_id'] as $column) {
        if (! $schema->hasColumn('repair_settings', $column)) {
            throw new RuntimeException('Missing paid repair setting column: '.$column);
        }
    }
    foreach (['public_id', 'outlet_id', 'repair_number', 'customer_id', 'device_label', 'identifier_type', 'identifier_value',
        'active_identifier', 'issue_description', 'diagnosis', 'status', 'invoice_id', 'handled_by_admin_id', 'version'] as $column) {
        if (! $schema->hasColumn('repair_jobs', $column)) {
            throw new RuntimeException('Missing paid repair job column: '.$column);
        }
    }
    foreach (['public_id', 'repair_job_id', 'version', 'status', 'parts_total', 'labor_total', 'grand_total', 'snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('repair_estimates', $column)) {
            throw new RuntimeException('Missing paid repair estimate column: '.$column);
        }
    }
    foreach (['repair_estimate_id', 'line_no', 'line_type', 'product_id', 'quantity', 'unit_price', 'line_total', 'snapshot', 'snapshot_sha256'] as $column) {
        if (! $schema->hasColumn('repair_estimate_lines', $column)) {
            throw new RuntimeException('Missing paid repair estimate line column: '.$column);
        }
    }
    if (! $schema->hasColumn('repair_estimate_approvals', 'approval_snapshot')
        || ! $schema->hasColumn('repair_part_consumptions', 'stock_movement_id')
        || ! $schema->hasColumn('repair_payment_links', 'tender_allocation_id')
        || ! $schema->hasColumn('repair_events', 'snapshot_sha256')) {
        throw new RuntimeException('Missing paid repair approval, stock, payment or history linkage.');
    }
    if (! DB::table('permission_definitions')->where('code', 'shop.repairs')->exists()) {
        throw new RuntimeException('Missing paid repair permission.');
    }
}

if (in_array($mode, ['documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['document_template_revisions', 'document_delivery_attempts']);
    foreach (['customer_email'] as $column) {
        if (! $schema->hasColumn('invoices', $column)) {
            throw new RuntimeException('Missing historical invoice email snapshot column: '.$column);
        }
    }
    foreach (['public_id', 'template_key', 'document_type', 'channel', 'template_part', 'version', 'template_text', 'created_by_admin_id'] as $column) {
        if (! $schema->hasColumn('document_template_revisions', $column)) {
            throw new RuntimeException('Missing document template revision column: '.$column);
        }
    }
    foreach (['public_id', 'outlet_id', 'document_type', 'document_public_id', 'channel', 'state', 'actor_admin_id', 'idempotency_key', 'request_sha256', 'intentional_resend', 'recipient', 'document_version', 'document_sha256', 'template_revisions', 'provider_reference', 'failure_summary'] as $column) {
        if (! $schema->hasColumn('document_delivery_attempts', $column)) {
            throw new RuntimeException('Missing document delivery audit column: '.$column);
        }
    }
    if (! DB::table('permission_definitions')->where('code', 'shop.documents.send')->exists()
        || DB::table('document_template_revisions')->distinct()->count('template_key') !== 6) {
        throw new RuntimeException('Document send permission or six-template catalogue is missing.');
    }
}

if (in_array($mode, ['cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['site_page_revisions', 'site_page_service_links', 'cms_policies', 'cms_policy_revisions',
        'software_products', 'software_product_revisions', 'software_releases', 'cms_route_redirects']);
    $cmsColumns = [
        'site_managed_pages' => ['public_id', 'version', 'current_revision_id', 'capability_scope', 'structured_content', 'protected_slug', 'created_by_admin_id', 'updated_by_admin_id'],
        'site_configuration_revisions' => ['created_by_admin_id', 'published_by_admin_id'],
        'site_media_assets' => ['uploaded_by_admin_id'],
        'site_navigation_items' => ['capability_scope', 'created_by_admin_id', 'updated_by_admin_id'],
        'site_settings' => ['updated_by_admin_id'],
        'site_page_revisions' => ['site_managed_page_id', 'version', 'state', 'snapshot', 'snapshot_sha256', 'created_by_admin_id', 'published_by_admin_id', 'restored_from_revision_id'],
        'site_page_service_links' => ['site_managed_page_id', 'digital_service_id', 'relationship'],
        'cms_policies' => ['public_id', 'policy_type', 'slug', 'requirement_state', 'applicability_state', 'approval_state', 'factual_review_state', 'effective_date', 'current_revision_id', 'protected_slug'],
        'cms_policy_revisions' => ['cms_policy_id', 'version', 'state', 'content', 'content_sha256', 'effective_date', 'approval_state', 'factual_review_state', 'unresolved_decisions', 'created_by_admin_id', 'published_by_admin_id'],
        'software_products' => ['public_id', 'name', 'slug', 'lifecycle_state', 'protected_slug', 'current_revision_id', 'current_release_id', 'archived_at'],
        'software_product_revisions' => ['software_product_id', 'revision_no', 'state', 'snapshot', 'snapshot_sha256', 'created_by_admin_id', 'published_by_admin_id', 'restored_from_revision_id'],
        'software_releases' => ['public_id', 'software_product_id', 'version', 'release_date', 'state', 'summary', 'notes', 'impact_review', 'snapshot_sha256', 'created_by_admin_id', 'published_by_admin_id'],
        'cms_route_redirects' => ['from_path', 'to_path', 'software_product_id', 'reason', 'created_by_admin_id'],
    ];
    foreach ($cmsColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (! $schema->hasColumn($table, $column)) {
                throw new RuntimeException('Missing MT-3.2 CMS column: '.$table.'.'.$column);
            }
        }
    }
}

if (in_array($mode, ['digital-services', 'projects', 'engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['digital_service_packages', 'digital_service_addons', 'digital_consultation_settings',
        'service_request_rate_buckets', 'service_request_details', 'service_request_selections', 'service_request_events', 'service_request_files']);
    $digitalColumns = [
        'digital_service_packages' => ['public_id', 'digital_service_id', 'code', 'name', 'pricing_type', 'price', 'active', 'sort_order', 'version'],
        'digital_service_addons' => ['public_id', 'digital_service_id', 'code', 'name', 'pricing_type', 'price', 'active', 'sort_order', 'version'],
        'digital_consultation_settings' => ['id', 'enabled', 'timezone', 'weekly_availability', 'version', 'updated_by_admin_id'],
        'service_request_rate_buckets' => ['submission_fingerprint', 'bucket_start', 'request_count'],
        'service_request_details' => ['service_request_id', 'submission_fingerprint', 'assigned_admin_id', 'follow_up_at', 'preferred_timezone', 'preferred_window_start_utc', 'preferred_window_end_utc', 'consultation_requested', 'consultation_status'],
        'service_request_selections' => ['service_request_id', 'selection_type', 'source_id', 'label_snapshot', 'pricing_type', 'price_snapshot', 'currency', 'source_version'],
        'service_request_events' => ['service_request_id', 'event_type', 'actor_admin_id', 'snapshot', 'snapshot_sha256', 'occurred_at'],
        'service_request_files' => ['id', 'service_request_id', 'object_key', 'original_name', 'mime_type', 'byte_size', 'sha256', 'uploaded_at'],
    ];
    foreach ($digitalColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (! $schema->hasColumn($table, $column)) {
                throw new RuntimeException('Missing MT-3.5 digital-service column: '.$table.'.'.$column);
            }
        }
    }
    foreach (['website.services.manage', 'website.digital-leads.manage', 'website.consultations.manage'] as $permission) {
        if (! DB::table('permission_definitions')->where('code', $permission)->exists()) {
            throw new RuntimeException('Missing MT-3.5 permission: '.$permission);
        }
    }
}

if (in_array($mode, ['projects', 'engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['client_projects', 'project_proposals', 'project_proposal_milestones', 'project_files', 'project_events']);
    $projectColumns = [
        'client_projects' => ['public_id', 'reference', 'service_request_id', 'digital_service_id', 'customer_account_id', 'assigned_admin_id', 'title', 'status', 'version'],
        'project_proposals' => ['public_id', 'client_project_id', 'revision_no', 'state', 'title', 'amount', 'currency', 'valid_until', 'snapshot', 'snapshot_sha256', 'quote_id', 'created_by_admin_id', 'approved_by_admin_id', 'approved_at'],
        'project_proposal_milestones' => ['milestone_id', 'project_proposal_id', 'kind', 'label', 'due_at'],
        'project_files' => ['id', 'client_project_id', 'project_proposal_id', 'file_type', 'object_key', 'original_name', 'mime_type', 'byte_size', 'sha256', 'uploaded_by_admin_id', 'uploaded_by_customer_account_id', 'retention_until'],
        'project_events' => ['client_project_id', 'event_type', 'actor_admin_id', 'actor_customer_account_id', 'snapshot', 'snapshot_sha256', 'occurred_at'],
    ];
    foreach ($projectColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (! $schema->hasColumn($table, $column)) {
                throw new RuntimeException('Missing MT-3.6 project-service column: '.$table.'.'.$column);
            }
        }
    }
    foreach (['website.digital-projects.manage', 'website.proposals.approve', 'website.client-files.manage', 'website.conversions.view'] as $permission) {
        if (! DB::table('permission_definitions')->where('code', $permission)->exists()) {
            throw new RuntimeException('Missing MT-3.6 permission: '.$permission);
        }
    }
}

if (in_array($mode, ['engagement', 'operations'], true)) {
    $expected = array_merge($expected, ['notification_preferences', 'product_notification_subscriptions',
        'notification_delivery_attempts', 'notification_rate_buckets', 'wishlist_items']);
    $engagementColumns = [
        'notification_preferences' => ['customer_account_id', 'email_enabled', 'max_per_hour', 'version'],
        'product_notification_subscriptions' => ['public_id', 'customer_account_id', 'product_id', 'event_type', 'channel', 'active', 'baseline_price', 'last_observed_price', 'last_observed_available', 'consented_at', 'unsubscribed_at', 'last_notified_at'],
        'notification_delivery_attempts' => ['public_id', 'subscription_id', 'event_key', 'event_type', 'price_snapshot', 'available_snapshot', 'status', 'attempt_count', 'payload', 'payload_sha256', 'provider_reference', 'failure_code', 'prepared_at', 'last_attempt_at', 'sent_at'],
        'notification_rate_buckets' => ['customer_account_id', 'bucket_start', 'attempt_count'],
        'wishlist_items' => ['public_id', 'owner_scope_hash', 'customer_account_id', 'guest_owner_hash', 'product_id', 'product_name_snapshot', 'price_snapshot', 'added_at'],
    ];
    foreach ($engagementColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (! $schema->hasColumn($table, $column)) {
                throw new RuntimeException('Missing MT-3.9 engagement column: '.$table.'.'.$column);
            }
        }
    }
    if (! DB::table('permission_definitions')->where('code', 'website.engagement.manage')->exists()) {
        throw new RuntimeException('Missing MT-3.9 engagement permission.');
    }
}

if ($mode === 'operations') {
    $expected = array_merge($expected, ['backup_manifests', 'configuration_recovery_snapshots', 'backup_restore_rehearsals']);
    $operationColumns = [
        'backup_manifests' => ['backup_record_id', 'contract_version', 'key_id', 'schema_sha256', 'code_sha256', 'manifest_sha256', 'manifest', 'verified_at'],
        'configuration_recovery_snapshots' => ['public_id', 'scope', 'key_id', 'schema_sha256', 'code_sha256', 'manifest_sha256', 'manifest', 'created_by_admin_id', 'verified_at'],
        'backup_restore_rehearsals' => ['public_id', 'backup_record_id', 'status', 'failure_code', 'environment', 'database_name', 'key_id', 'manifest_sha256', 'checked_by_admin_id', 'checked_at', 'result'],
    ];
    foreach ($operationColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (! $schema->hasColumn($table, $column)) {
                throw new RuntimeException('Missing MT-3.3 operations column: '.$table.'.'.$column);
            }
        }
    }
}

if (in_array($mode, ['trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
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
    if (! DB::table('permission_definitions')->where('code', 'shop.trade-in')->exists()) {
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
    $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
    $definitions[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create['Create Table']);
    if (! str_contains($definitions[$table], 'ENGINE=InnoDB')) {
        throw new RuntimeException('Non-transactional table found.');
    }
    $seedRows = in_array($mode, ['admin-google', 'orders', 'team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true) ? ['business_profiles' => 1, 'integration_connections' => 2] : [];
    if (in_array($mode, ['team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
        $seedRows += ['permission_definitions' => count($checkpointPermissions), 'roles' => 12,
            'role_permissions' => DB::table('role_permissions')->count()];
    }
    if (in_array($mode, ['documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)) {
        $seedRows += ['document_template_revisions' => 6];
    }
    $rowCount = DB::table($table)->count();
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
    'business_rows' => 0, 'canonical_seed_rows' => in_array($mode, ['team-members', 'procurement', 'stocktake', 'transfer', 'payments', 'cash', 'trade-in', 'promotion', 'loyalty', 'repair', 'documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true)
        ? 3 + count($checkpointPermissions) + 12 + DB::table('role_permissions')->count() + (in_array($mode, ['documents', 'cms', 'digital-services', 'projects', 'engagement', 'operations'], true) ? 6 : 0)
        : (in_array($mode, ['admin-google', 'orders'], true) ? 3 : 0)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
