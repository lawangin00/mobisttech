<?php

namespace App\Addendum;

final class Permissions
{
    public const POS = [
        'shop.stocktake' => 'Count stock', 'shop.stocktake.approve' => 'Approve stock variances',
        'shop.transfers.dispatch' => 'Dispatch stock transfers', 'shop.transfers.receive' => 'Receive stock transfers',
        'shop.procurement' => 'Manage supplier procurement', 'shop.cash' => 'Operate cash sessions',
        'shop.cash.approve' => 'Approve cash variances and expenses', 'shop.trade-in' => 'Manage trade-in intake',
        'shop.labels' => 'Print retail labels', 'shop.bulk-data' => 'Preview and execute approved bulk operations',
        'shop.repairs' => 'Manage paid repair jobs', 'config.promotions.manage' => 'Manage promotions',
        'config.loyalty.manage' => 'Manage loyalty configuration',
        'system.reset.preview' => 'Preview a reset', 'system.reset.transactional' => 'Execute transactional reset',
        'system.reset.business' => 'Execute business reset', 'system.reset.factory' => 'Execute factory reset',
    ];

    // Existing role grants are untouched. New publication is intentionally separate from content editing.
    public const WEBSITE = [
        'website.mode.preview' => ['owner', 'manager', 'content_editor'],
        'website.mode.publish' => ['owner', 'manager'],
        'website.digital-content.manage' => ['owner', 'manager', 'content_editor'],
        'website.digital-leads.manage' => ['owner', 'manager', 'operations'],
        'website.digital-projects.manage' => ['owner', 'manager', 'operations'],
        'website.proposals.approve' => ['owner', 'manager'],
        'website.client-files.manage' => ['owner', 'manager', 'operations'],
        'website.consultations.manage' => ['owner', 'manager', 'operations'],
        'website.engagement.manage' => ['owner', 'manager'],
        'website.conversions.view' => ['owner', 'manager'],
    ];
}
