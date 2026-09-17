<?php

namespace App\Addendum;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;

/** Classification only. This class does not authorize, preview records, back up or execute a reset. */
final class ResetRetention
{
    public const LEVELS = ['transactional', 'business', 'factory'];

    public const GROUPS = [
        'transactional' => ['repair_events', 'repair_payment_links', 'repair_part_consumptions', 'repair_estimate_approvals', 'repair_estimate_lines', 'repair_estimates', 'repair_jobs', 'loyalty_claim_lots', 'loyalty_earn_lots', 'loyalty_entries', 'loyalty_claims', 'loyalty_accounts', 'promotion_events', 'promotion_claims', 'claim_events', 'claims', 'invoices', 'sales', 'reservation_allocations', 'reservation_lines', 'reservations', 'order_items', 'orders', 'payments',
            'product_reviews', 'project_quotes', 'service_requests', 'payment_receipts', 'returns', 'return_lines', 'refunds', 'pos_tender_allocations', 'pos_settlement_events', 'pos_refund_allocations', 'cash_sessions', 'cash_entries', 'monetary_adjustments', 'project_milestone_identities', 'order_item_milestones'],
        'inventory' => ['trade_in_events', 'trade_in_identifiers', 'trade_ins', 'products', 'stock_units', 'stock_movements', 'stock_acquisitions', 'product_imeis', 'active_imeis', 'product_listings', 'stock_unit_lineage', 'inventory_custody_holds', 'acquisition_source_references',
            'suppliers', 'supplier_contacts', 'purchase_orders', 'purchase_order_lines', 'purchase_order_receipts', 'purchase_order_receipt_lines', 'purchase_order_events', 'reorder_policies', 'stocktake_sessions', 'stocktake_lines', 'stocktake_unit_baselines', 'stocktake_counts', 'stocktake_recounts', 'stocktake_approvals',
            'stock_transfers', 'stock_transfer_lines', 'stock_transfer_units', 'stock_transfer_receipts', 'stock_transfer_receipt_lines'],
        'business_content' => ['digital_services', 'site_managed_pages', 'site_navigation_items', 'site_media_assets', 'site_media_usages', 'customers', 'customer_source_links'],
        'configuration' => ['document_template_revisions', 'repair_settings', 'loyalty_configurations', 'promotions', 'promotion_products', 'promotion_categories', 'pos_configuration_revisions', 'site_configuration_revisions', 'pos_master_data_options', 'pos_master_data_usages', 'pos_media_assets', 'pos_media_usages',
            'pos_settings', 'site_settings', 'site_secret_settings', 'website_operating_profiles', 'business_profiles', 'integration_connections', 'pos_payment_destinations'],
        'bootstrap' => ['admins', 'outlets', 'outlet_admins', 'permission_definitions', 'roles', 'role_permissions', 'admin_roles'],
        'mixed_identity' => ['users'],
        'preserved_evidence' => ['document_delivery_attempts', 'super_admins', 'backup_records', 'pos_audit_logs', 'admin_audit_logs', 'identity_audit_events', 'team_member_audit_events', 'admin_identity_mappings', 'integration_events', 'migration_runs', 'migration_identity_map', 'migration_quarantine', 'migration_reconciliation', 'migration_source_history', 'legacy_integration_requests', 'legacy_integration_events'],
        'runtime' => ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'migrations', 'account_sessions', 'password_reset_tokens',
            'admin_password_reset_tokens', 'super_admin_password_reset_tokens', 'site_admin_password_reset_tokens', 'integration_oauth_states', 'personal_access_tokens', 'idempotency_requests', 'domain_events', 'publication_versions', 'document_sequences', 'resource_capabilities'],
    ];

    public function classify(string $level, array $tables): array
    {
        if (! in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException('Unknown reset level.');
        }
        $result = [];
        foreach ($tables as $table) {
            $groups = array_keys(array_filter(self::GROUPS, fn ($names) => in_array($table, $names, true)));
            if (count($groups) !== 1) {
                throw new LogicException('Unknown or ambiguous retention classification: '.$table);
            }
            $group = $groups[0];
            $action = match ($group) {
                'transactional' => 'candidate_clear',
                'inventory', 'business_content' => $level === 'transactional' ? 'preserve' : 'candidate_clear',
                'configuration' => $level === 'factory' ? 'bootstrap_review' : 'preserve',
                'mixed_identity' => $level === 'transactional' ? 'preserve' : 'row_selection_required',
                'bootstrap' => $level === 'factory' ? 'bootstrap_review' : 'preserve',
                default => 'preserve',
            };
            $result[$table] = ['group' => $group, 'action' => $action];
        }

        return ['contract' => 'reset-retention.v1', 'level' => $level, 'executable' => false, 'tables' => $result];
    }

    public function dependencyBarriers(string $level): array
    {
        $database = DB::connection()->getDatabaseName();
        $tables = array_column(Schema::getTables(schema: $database), 'name');
        $plan = $this->classify($level, $tables);
        $barriers = [];
        foreach ($tables as $child) {
            foreach (Schema::getForeignKeys($child) as $foreign) {
                $parent = $foreign['foreign_table'];
                if (! isset($plan['tables'][$parent])) {
                    throw new LogicException('Unclassified foreign schema dependency.');
                }
                if ($plan['tables'][$child]['action'] !== 'candidate_clear' && $plan['tables'][$parent]['action'] === 'candidate_clear') {
                    $barriers[] = ['retained_child' => $child, 'cleared_parent' => $parent, 'columns' => $foreign['columns'],
                        'resolution' => 'Block until an approved row/dependency preservation plan proves integrity; never disable foreign keys or null history to force a reset.'];
                }
            }
        }

        return ['contract' => $plan['contract'], 'level' => $level, 'executable' => false, 'barriers' => $barriers];
    }
}
