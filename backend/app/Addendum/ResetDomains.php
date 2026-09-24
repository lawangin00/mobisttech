<?php

namespace App\Addendum;

use InvalidArgumentException;

final class ResetDomains
{
    public const MAP = [
        'commerce' => [
            'reservation_allocations', 'reservation_lines', 'reservations', 'order_items', 'website_order_payment_terms', 'orders', 'payments',
            'payment_receipts', 'returns', 'return_lines', 'refunds', 'invoices', 'sales',
            'pos_tender_allocations', 'pos_settlement_events', 'pos_refund_allocations', 'monetary_adjustments',
        ],
        'claims' => ['claim_events', 'claims'],
        'repairs' => [
            'repair_events', 'repair_payment_links', 'repair_part_consumptions', 'repair_estimate_approvals',
            'repair_estimate_lines', 'repair_estimates', 'repair_jobs',
        ],
        'loyalty_promotions' => [
            'loyalty_claim_lots', 'loyalty_earn_lots', 'loyalty_entries', 'loyalty_claims', 'loyalty_accounts',
            'promotion_events', 'promotion_claims',
        ],
        'cash' => ['cash_sessions', 'cash_entries'],
        'digital_projects' => [
            'project_events', 'project_files', 'project_proposal_milestones', 'project_proposals', 'client_projects',
            'project_quotes', 'project_milestone_identities', 'order_item_milestones',
        ],
        'service_requests' => [
            'service_request_files', 'service_request_events', 'service_request_selections', 'service_request_details',
            'service_request_rate_buckets', 'service_requests', 'product_reviews',
        ],
        'notification_delivery' => ['notification_delivery_attempts'],
        'inventory' => [
            'products', 'stock_units', 'stock_movements', 'stock_acquisitions', 'product_imeis', 'active_imeis',
            'product_listings', 'stock_unit_lineage', 'inventory_custody_holds', 'acquisition_source_references',
            'stocktake_sessions', 'stocktake_lines', 'stocktake_unit_baselines', 'stocktake_counts',
            'stocktake_recounts', 'stocktake_approvals', 'stock_transfers', 'stock_transfer_lines',
            'stock_transfer_units', 'stock_transfer_receipts', 'stock_transfer_receipt_lines',
        ],
        'procurement' => [
            'suppliers', 'supplier_contacts', 'purchase_orders', 'purchase_order_lines', 'purchase_order_receipts',
            'purchase_order_receipt_lines', 'purchase_order_events', 'reorder_policies',
        ],
        'trade_in' => ['trade_in_events', 'trade_in_identifiers', 'trade_ins'],
        'customers' => ['customers', 'customer_source_links', 'users'],
        'website_content' => [
            'site_page_service_links', 'site_page_revisions', 'cms_policy_revisions', 'cms_policies',
            'cms_route_redirects', 'software_releases', 'software_product_revisions', 'software_products',
            'site_managed_pages', 'site_navigation_items', 'site_media_assets', 'site_media_usages',
        ],
        'digital_catalogue' => ['digital_service_packages', 'digital_service_addons', 'digital_services'],
        'engagement' => ['wishlist_items', 'product_notification_subscriptions', 'notification_preferences'],
        'factory_configuration' => [
            'promotions', 'promotion_products', 'promotion_categories', 'pos_configuration_revisions',
            'pos_media_assets', 'pos_media_usages', 'pos_settings', 'site_settings', 'site_secret_settings',
            'pos_payment_destinations',
        ],
    ];

    public const LEVEL_DOMAINS = [
        'transactional' => ['commerce', 'claims', 'repairs', 'loyalty_promotions', 'cash', 'digital_projects', 'service_requests', 'notification_delivery'],
        'business' => ['commerce', 'claims', 'repairs', 'loyalty_promotions', 'cash', 'digital_projects', 'service_requests', 'notification_delivery',
            'inventory', 'procurement', 'trade_in', 'customers', 'website_content', 'digital_catalogue', 'engagement'],
        'factory' => ['commerce', 'claims', 'repairs', 'loyalty_promotions', 'cash', 'digital_projects', 'service_requests', 'notification_delivery',
            'inventory', 'procurement', 'trade_in', 'customers', 'website_content', 'digital_catalogue', 'engagement', 'factory_configuration'],
    ];

    public function normalize(string $level, array $domains): array
    {
        if (! isset(self::LEVEL_DOMAINS[$level])) {
            throw new InvalidArgumentException('Unknown reset level.');
        }
        $domains = array_values(array_unique(array_map(fn ($value) => strtolower(trim((string) $value)), $domains)));
        sort($domains, SORT_STRING);
        if ($domains === [] || array_diff($domains, self::LEVEL_DOMAINS[$level])) {
            throw new InvalidArgumentException('Reset scope contains an unavailable domain.');
        }
        if ($level === 'factory') {
            $expected = self::LEVEL_DOMAINS['factory'];
            sort($expected, SORT_STRING);
            if ($domains !== $expected) {
                throw new InvalidArgumentException('Factory reset requires the complete approved factory scope.');
            }
        }

        return $domains;
    }

    public function tables(string $level, array $domains): array
    {
        $tables = [];
        foreach ($this->normalize($level, $domains) as $domain) {
            $tables = [...$tables, ...self::MAP[$domain]];
        }
        $tables = array_values(array_unique($tables));
        sort($tables, SORT_STRING);

        return $tables;
    }

    public function available(string $level): array
    {
        if (! isset(self::LEVEL_DOMAINS[$level])) {
            throw new InvalidArgumentException('Unknown reset level.');
        }

        return self::LEVEL_DOMAINS[$level];
    }
}
