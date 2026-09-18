<?php

use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CustomerApiController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PlatformAdministrationController;
use App\Http\Controllers\PosCustomerReportingController;
use App\Http\Controllers\PosOperationsController;
use App\Http\Controllers\PosShellController;
use App\Http\Controllers\PosStockControlController;
use App\Http\Controllers\PosTransactionController;
use App\Http\Controllers\TeamMemberController;
use Illuminate\Support\Facades\Route;

foreach (['customer', 'admin'] as $realm) {
    $prefix = $realm === 'customer' ? '/api/v1' : '/internal/'.$realm;
    $routes = [
        ['GET', '/auth/csrf-cookie', 'csrf', false],
        ['POST', '/auth/login', 'login', false],
        ['POST', '/auth/logout', 'logout', true],
        ['POST', '/auth/forgot-password', 'forgot', false],
        ['POST', '/auth/reset-password', 'reset', false],
        ['PATCH', '/auth/password', 'changePassword', true],
        ['GET', '/account', 'account', true],
        ['POST', '/auth/activity', 'activity', true],
        ['POST', '/auth/confirm-password', 'confirmPassword', true],
    ];
    if ($realm === 'customer') {
        $routes[] = ['POST', '/auth/register', 'register', false];
    }
    if ($realm === 'admin') {
        $routes[] = ['GET', '/outlets', 'outlets', true];
        $routes[] = ['POST', '/outlets/select', 'selectOutlet', true];
    }
    foreach ($routes as [$method, $path, $action, $authenticated]) {
        $route = Route::match([$method], $prefix.$path, [IdentityController::class, $action])
            ->defaults('identity_realm', $realm)->middleware('identity')->name('identity.'.$realm.'.'.$action);
        if ($authenticated) {
            $route->middleware('identity.auth');
        }
        if ($method !== 'GET') {
            $route->middleware('throttle:identity');
        }
    }
}
Route::get('/internal/admin/pos/login', [PosShellController::class, 'login'])
    ->defaults('identity_realm', 'admin')->middleware('identity')->name('admin.pos.login');

Route::prefix('/api/v1')->middleware(['identity', 'identity.auth', 'throttle:api-customer'])->group(function () {
    Route::post('/cart/quote', [CustomerApiController::class, 'cartQuote'])->defaults('identity_realm', 'customer')->name('api.customer.cart.quote');
    Route::post('/orders', [CustomerApiController::class, 'checkout'])->defaults('identity_realm', 'customer')->name('api.customer.orders.store');
    Route::get('/orders', [CustomerApiController::class, 'orders'])->defaults('identity_realm', 'customer')->name('api.customer.orders.index');
    Route::get('/orders/{order}', [CustomerApiController::class, 'order'])->defaults('identity_realm', 'customer')->name('api.customer.orders.show');
    Route::post('/orders/{order}/cancel', [CustomerApiController::class, 'cancel'])->defaults('identity_realm', 'customer')->name('api.customer.orders.cancel');
    Route::post('/orders/{order}/payments/retry', [CustomerApiController::class, 'retryPayment'])->defaults('identity_realm', 'customer')->name('api.customer.payments.retry');
    Route::post('/payments/{payment}/initiate', [CustomerApiController::class, 'initiatePayment'])->defaults('identity_realm', 'customer')->name('api.customer.payments.initiate');
    Route::post('/project-milestones/pay', [CustomerApiController::class, 'milestone'])->defaults('identity_realm', 'customer')->name('api.customer.milestones.pay');
    Route::get('/projects/{project}', [CustomerApiController::class, 'project'])->defaults('identity_realm', 'customer')->name('api.customer.projects.show');
    Route::get('/projects/{project}/files/{file}', [CustomerApiController::class, 'projectFile'])->defaults('identity_realm', 'customer')->name('api.customer.projects.files');
    Route::get('/wishlist', [CustomerApiController::class, 'wishlist'])->defaults('identity_realm', 'customer')->name('api.customer.wishlist.index');
    Route::post('/wishlist/{product}', [CustomerApiController::class, 'saveWishlist'])->defaults('identity_realm', 'customer')->name('api.customer.wishlist.store');
    Route::delete('/wishlist/{product}', [CustomerApiController::class, 'removeWishlist'])->defaults('identity_realm', 'customer')->name('api.customer.wishlist.destroy');
    Route::get('/notification-preferences', [CustomerApiController::class, 'notificationPreferences'])->defaults('identity_realm', 'customer')->name('api.customer.notifications.preferences');
    Route::put('/notification-preferences', [CustomerApiController::class, 'updateNotificationPreferences'])->defaults('identity_realm', 'customer')->name('api.customer.notifications.preferences.update');
    Route::get('/product-subscriptions', [CustomerApiController::class, 'subscriptions'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.index');
    Route::post('/product-subscriptions/{product}', [CustomerApiController::class, 'subscribe'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.store');
    Route::delete('/product-subscriptions/{product}', [CustomerApiController::class, 'unsubscribe'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.destroy');
    Route::get('/reviews', [CustomerApiController::class, 'reviews'])->defaults('identity_realm', 'customer')->name('api.customer.reviews.index');
    Route::post('/reviews', [CustomerApiController::class, 'submitReview'])->defaults('identity_realm', 'customer')->name('api.customer.reviews.store');
});
Route::prefix('/internal/admin')->middleware(['identity', 'identity.auth'])->group(function () {
    Route::get('/pos', [PosShellController::class, 'home'])->defaults('identity_realm', 'admin')->name('admin.pos.home');
    Route::get('/pos/workspace/{area}', [PosShellController::class, 'workspace'])->whereIn('area', ['sales', 'inventory', 'invoices', 'warranty', 'claims', 'reports', 'operations'])
        ->defaults('identity_realm', 'admin')->name('admin.pos.workspace');
    Route::get('/pos/catalogue', [PosTransactionController::class, 'catalogue'])->defaults('identity_realm', 'admin')->name('admin.pos.catalogue');
    Route::get('/pos/lookup', [PosTransactionController::class, 'lookup'])->defaults('identity_realm', 'admin')->name('admin.pos.lookup');
    Route::post('/pos/inventory/products', [PosTransactionController::class, 'saveProduct'])->defaults('identity_realm', 'admin')->name('admin.pos.products.save');
    Route::post('/pos/inventory/products/{product}/acquire', [PosTransactionController::class, 'acquire'])->defaults('identity_realm', 'admin')->name('admin.pos.inventory.acquire');
    Route::post('/pos/inventory/products/{product}/imeis', [PosTransactionController::class, 'imeis'])->defaults('identity_realm', 'admin')->name('admin.pos.inventory.imeis');
    Route::post('/pos/inventory/products/{product}/adjust', [PosTransactionController::class, 'adjust'])->defaults('identity_realm', 'admin')->name('admin.pos.inventory.adjust');
    Route::patch('/pos/inventory/units/{unit}', [PosTransactionController::class, 'unitAttributes'])->defaults('identity_realm', 'admin')->name('admin.pos.inventory.units');
    Route::post('/pos/sales/quote', [PosTransactionController::class, 'quote'])->defaults('identity_realm', 'admin')->name('admin.pos.sales.quote');
    Route::post('/pos/sales', [PosTransactionController::class, 'sell'])->defaults('identity_realm', 'admin')->name('admin.pos.sales.store');
    Route::get('/pos/invoices/{invoice}', [PosTransactionController::class, 'invoice'])->defaults('identity_realm', 'admin')->name('admin.pos.invoices.show');
    Route::post('/pos/returns', [PosTransactionController::class, 'acceptReturn'])->defaults('identity_realm', 'admin')->name('admin.pos.returns.store');
    Route::post('/pos/refunds', [PosTransactionController::class, 'refund'])->defaults('identity_realm', 'admin')->name('admin.pos.refunds.store');
    Route::get('/pos/labels/{kind}/{id}', [PosTransactionController::class, 'label'])->defaults('identity_realm', 'admin')->name('admin.pos.labels.show');
    Route::get('/pos/customer-reporting/{area}', [PosCustomerReportingController::class, 'index'])->whereIn('area', ['invoices', 'warranty', 'claims', 'reports'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.index');
    Route::post('/pos/customer-reporting/claims', [PosCustomerReportingController::class, 'openClaim'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.claims.open');
    Route::get('/pos/customer-reporting/claims/{claim}', [PosCustomerReportingController::class, 'claim'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.claims.show');
    Route::post('/pos/customer-reporting/claims/{claim}', [PosCustomerReportingController::class, 'updateClaim'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.claims.update');
    Route::get('/pos/customer-reporting/documents/{type}/{document}/preview', [PosCustomerReportingController::class, 'documentPreview'])->whereIn('type', ['invoice', 'warranty'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.documents.preview');
    Route::get('/pos/customer-reporting/documents/{type}/{document}/print', [PosCustomerReportingController::class, 'documentPrint'])->whereIn('type', ['invoice', 'warranty'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.documents.print');
    Route::get('/pos/customer-reporting/documents/{type}/{document}/pdf', [PosCustomerReportingController::class, 'documentPdf'])->whereIn('type', ['invoice', 'warranty'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.documents.pdf');
    Route::get('/pos/customer-reporting/documents/{type}/{document}/email-draft', [PosCustomerReportingController::class, 'emailDraft'])->whereIn('type', ['invoice', 'warranty'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.documents.email-draft');
    Route::post('/pos/customer-reporting/documents/{type}/{document}/email', [PosCustomerReportingController::class, 'emailSend'])->whereIn('type', ['invoice', 'warranty'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.documents.email');
    Route::post('/pos/customer-reporting/documents/{type}/{document}/whatsapp', [PosCustomerReportingController::class, 'whatsapp'])->whereIn('type', ['invoice', 'warranty'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.documents.whatsapp');
    Route::post('/pos/customer-reporting/documents/whatsapp/{attempt}/opened', [PosCustomerReportingController::class, 'whatsappOpened'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.documents.whatsapp-opened');
    Route::get('/pos/customer-reporting/documents/{type}/{document}/history', [PosCustomerReportingController::class, 'deliveryHistory'])->whereIn('type', ['invoice', 'warranty'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.documents.history');
    Route::get('/pos/customer-reporting/report/summary', [PosCustomerReportingController::class, 'report'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.report');
    Route::get('/pos/customer-reporting/report/csv', [PosCustomerReportingController::class, 'reportCsv'])->defaults('identity_realm', 'admin')->name('admin.pos.customer-reporting.report.csv');
    Route::get('/pos/operations', [PosOperationsController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.pos.operations');
    Route::post('/pos/operations/cash/open', [PosOperationsController::class, 'cashOpen'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.cash.open');
    Route::get('/pos/operations/cash/{session}', [PosOperationsController::class, 'cashSession'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.cash.show');
    Route::post('/pos/operations/cash/{session}/entries', [PosOperationsController::class, 'cashEntry'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.cash.entries');
    Route::post('/pos/operations/cash/{session}/entries/{entry}/review', [PosOperationsController::class, 'cashReview'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.cash.review');
    Route::post('/pos/operations/cash/{session}/close', [PosOperationsController::class, 'cashClose'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.cash.close');
    Route::post('/pos/operations/settlements/{allocation}', [PosOperationsController::class, 'settle'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.settlements');
    Route::post('/pos/operations/trade-ins/{product}', [PosOperationsController::class, 'tradeCreate'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.trade-ins.create');
    Route::get('/pos/operations/trade-ins/{tradeIn}', [PosOperationsController::class, 'tradeShow'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.trade-ins.show');
    Route::post('/pos/operations/trade-ins/{tradeIn}/approve', [PosOperationsController::class, 'tradeApprove'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.trade-ins.approve');
    Route::post('/pos/operations/trade-ins/{tradeIn}/receive', [PosOperationsController::class, 'tradeReceive'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.trade-ins.receive');
    Route::post('/pos/operations/trade-ins/{tradeIn}/cancel', [PosOperationsController::class, 'tradeCancel'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.trade-ins.cancel');
    Route::post('/pos/operations/repairs/configure', [PosOperationsController::class, 'repairConfigure'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.repairs.configure');
    Route::post('/pos/operations/repairs', [PosOperationsController::class, 'repairOpen'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.repairs.open');
    Route::get('/pos/operations/repairs/{repair}', [PosOperationsController::class, 'repairShow'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.repairs.show');
    Route::post('/pos/operations/repairs/{repair}/status', [PosOperationsController::class, 'repairUpdate'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.repairs.update');
    Route::post('/pos/operations/repairs/{repair}/estimate', [PosOperationsController::class, 'repairEstimate'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.repairs.estimate');
    Route::post('/pos/operations/repairs/{repair}/estimate/{estimate}/decision', [PosOperationsController::class, 'repairEstimateDecision'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.repairs.estimate.decision');
    Route::post('/pos/operations/repairs/{repair}/parts', [PosOperationsController::class, 'repairConsumeParts'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.repairs.parts');
    Route::post('/pos/operations/repairs/{repair}/collect', [PosOperationsController::class, 'repairCollect'])->defaults('identity_realm', 'admin')->name('admin.pos.operations.repairs.collect');
    Route::get('/pos/stock-control', [PosStockControlController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control');
    Route::post('/pos/stock-control/suppliers', [PosStockControlController::class, 'supplier'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.suppliers');
    Route::post('/pos/stock-control/orders', [PosStockControlController::class, 'order'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.orders');
    Route::post('/pos/stock-control/orders/{order}/receive', [PosStockControlController::class, 'receiveOrder'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.orders.receive');
    Route::post('/pos/stock-control/reorder/{product}', [PosStockControlController::class, 'reorder'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.reorder');
    Route::post('/pos/stock-control/stocktakes', [PosStockControlController::class, 'stocktakeStart'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.stocktakes');
    Route::get('/pos/stock-control/stocktakes/{stocktake}', [PosStockControlController::class, 'stocktake'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.stocktakes.show');
    Route::post('/pos/stock-control/stocktakes/{stocktake}/lines/{line}', [PosStockControlController::class, 'stocktakeCount'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.stocktakes.count');
    Route::post('/pos/stock-control/stocktakes/{stocktake}/recount', [PosStockControlController::class, 'stocktakeRecount'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.stocktakes.recount');
    Route::post('/pos/stock-control/stocktakes/{stocktake}/approve', [PosStockControlController::class, 'stocktakeApprove'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.stocktakes.approve');
    Route::post('/pos/stock-control/transfers', [PosStockControlController::class, 'transferCreate'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.transfers');
    Route::get('/pos/stock-control/transfers/{transfer}', [PosStockControlController::class, 'transfer'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.transfers.show');
    Route::post('/pos/stock-control/transfers/{transfer}/dispatch', [PosStockControlController::class, 'transferDispatch'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.transfers.dispatch');
    Route::post('/pos/stock-control/transfers/{transfer}/receive', [PosStockControlController::class, 'transferReceive'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.transfers.receive');
    Route::post('/pos/stock-control/bulk/preview', [PosStockControlController::class, 'bulkPreview'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.bulk.preview');
    Route::post('/pos/stock-control/bulk/import', [PosStockControlController::class, 'bulkImport'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.bulk.import');
    Route::post('/pos/stock-control/bulk/export', [PosStockControlController::class, 'bulkExport'])->defaults('identity_realm', 'admin')->name('admin.pos.stock-control.bulk.export');
    Route::get('/platform', [PlatformAdministrationController::class, 'page'])->defaults('identity_realm', 'admin')->name('admin.platform.page');
    Route::get('/platform/data', [PlatformAdministrationController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.platform.data');
    Route::post('/platform/website-mode/draft', [PlatformAdministrationController::class, 'modeDraft'])->defaults('identity_realm', 'admin')->name('admin.platform.mode.draft');
    Route::get('/platform/website-mode/{revision}/preview', [PlatformAdministrationController::class, 'modePreview'])->defaults('identity_realm', 'admin')->name('admin.platform.mode.preview');
    Route::post('/platform/website-mode/{revision}/publish', [PlatformAdministrationController::class, 'modePublish'])->defaults('identity_realm', 'admin')->name('admin.platform.mode.publish');
    Route::post('/platform/website-mode/{revision}/rollback', [PlatformAdministrationController::class, 'modeRollback'])->defaults('identity_realm', 'admin')->name('admin.platform.mode.rollback');
    Route::post('/platform/policies/{type}/draft', [PlatformAdministrationController::class, 'policyDraft'])->defaults('identity_realm', 'admin')->name('admin.platform.policies.draft');
    Route::post('/platform/policies/{revision}/publish', [PlatformAdministrationController::class, 'policyPublish'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.platform.policies.publish');
    Route::post('/platform/policies/{revision}/rollback', [PlatformAdministrationController::class, 'policyRollback'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.platform.policies.rollback');
    Route::post('/platform/templates/{key}', [PlatformAdministrationController::class, 'template'])->defaults('identity_realm', 'admin')->name('admin.platform.templates.update');
    Route::post('/platform/payment-destinations', [PlatformAdministrationController::class, 'destinationCreate'])->defaults('identity_realm', 'admin')->name('admin.platform.destinations.create');
    Route::patch('/platform/payment-destinations/{destination}', [PlatformAdministrationController::class, 'destinationUpdate'])->defaults('identity_realm', 'admin')->name('admin.platform.destinations.update');
    Route::post('/platform/promotions', [PlatformAdministrationController::class, 'promotion'])->defaults('identity_realm', 'admin')->name('admin.platform.promotions');
    Route::put('/platform/loyalty', [PlatformAdministrationController::class, 'loyalty'])->defaults('identity_realm', 'admin')->name('admin.platform.loyalty');
    Route::post('/platform/software', [PlatformAdministrationController::class, 'softwareDraft'])->defaults('identity_realm', 'admin')->name('admin.platform.software.create');
    Route::post('/platform/software/{software}/draft', [PlatformAdministrationController::class, 'softwareDraft'])->defaults('identity_realm', 'admin')->name('admin.platform.software.draft');
    Route::post('/platform/software/revisions/{revision}/publish', [PlatformAdministrationController::class, 'softwarePublish'])->defaults('identity_realm', 'admin')->name('admin.platform.software.publish');
    Route::post('/platform/software/revisions/{revision}/rollback', [PlatformAdministrationController::class, 'softwareRollback'])->defaults('identity_realm', 'admin')->name('admin.platform.software.rollback');
    Route::post('/platform/software/{software}/releases', [PlatformAdministrationController::class, 'releaseDraft'])->defaults('identity_realm', 'admin')->name('admin.platform.software.release');
    Route::post('/platform/software/releases/{release}/publish', [PlatformAdministrationController::class, 'releasePublish'])->defaults('identity_realm', 'admin')->name('admin.platform.software.release.publish');
    Route::post('/platform/software/{software}/archive', [PlatformAdministrationController::class, 'softwareArchive'])->defaults('identity_realm', 'admin')->name('admin.platform.software.archive');
    Route::post('/platform/software/{software}/slug', [PlatformAdministrationController::class, 'softwareSlug'])->defaults('identity_realm', 'admin')->name('admin.platform.software.slug');
    Route::get('/team-members', [TeamMemberController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.team-members.index');
    Route::get('/roles', [TeamMemberController::class, 'roles'])->defaults('identity_realm', 'admin')->name('admin.roles.index');
    Route::post('/team-members', [TeamMemberController::class, 'store'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.team-members.store');
    Route::patch('/team-members/{member:public_id}', [TeamMemberController::class, 'update'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.team-members.update');
    Route::post('/roles', [TeamMemberController::class, 'storeRole'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.roles.store');
    Route::patch('/roles/{role:public_id}', [TeamMemberController::class, 'updateRole'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.roles.update');
    Route::delete('/roles/{role:public_id}', [TeamMemberController::class, 'destroyRole'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.roles.destroy');
    Route::get('/settings/integrations', [IntegrationController::class, 'page'])->defaults('identity_realm', 'admin')->name('admin.integrations.page');
    Route::get('/integrations', [IntegrationController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.integrations.index');
    Route::post('/integrations/{provider}/connect', [IntegrationController::class, 'connect'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.connect');
    Route::get('/integrations/{provider}/callback', [IntegrationController::class, 'callback'])->defaults('identity_realm', 'admin')->name('admin.integrations.callback');
    Route::post('/integrations/{provider}/test', [IntegrationController::class, 'test'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.test');
    Route::post('/integrations/google_drive/backup-now', [IntegrationController::class, 'backupNow'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.backup-now');
    Route::put('/integrations/google_drive/backup-settings', [IntegrationController::class, 'backupSettings'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.backup-settings');
    Route::delete('/integrations/{provider}', [IntegrationController::class, 'disconnect'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.integrations.disconnect');
    Route::put('/business-profile', [BusinessProfileController::class, 'update'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.business-profile.update');
});
Route::get('/sanctum/csrf-cookie', [IdentityController::class, 'csrf'])
    ->defaults('identity_realm', 'customer')->middleware('identity')->name('identity.csrf');
