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
        'config.loyalty.manage' => 'Manage loyalty configuration', 'config.payments.manage' => 'Manage POS payment destinations',
        'shop.payments.reconcile' => 'Reconcile POS payment settlements', 'shop.payments.refund-override' => 'Override POS refund destination or method',
        'shop.payments.refund-approve' => 'Approve POS refund overrides',
        'system.reset.preview' => 'Preview a reset', 'system.reset.transactional' => 'Execute transactional reset',
        'system.reset.business' => 'Execute business reset', 'system.reset.factory' => 'Execute factory reset',
    ];
}
