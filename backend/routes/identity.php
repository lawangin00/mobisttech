<?php

use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CustomerApiController;
use App\Http\Controllers\DigitalOperationsController;
use App\Http\Controllers\IdentityController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\OutletManagementController;
use App\Http\Controllers\PlatformAdministrationController;
use App\Http\Controllers\PosAuditController;
use App\Http\Controllers\PosCustomerReportingController;
use App\Http\Controllers\PosDashboardReportPreferencesController;
use App\Http\Controllers\PosMasterDataController;
use App\Http\Controllers\PosOperationsController;
use App\Http\Controllers\PosPortalPreferencesController;
use App\Http\Controllers\PosShellController;
use App\Http\Controllers\PosStockControlController;
use App\Http\Controllers\PosTransactionController;
use App\Http\Controllers\ResetAdministrationController;
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
Route::post('/internal/admin/auth/offline-owner-recovery/rotate', [IdentityController::class, 'rotateOfflineOwnerCodes'])->defaults('identity_realm', 'admin')->middleware(['identity', 'identity.auth', 'identity.recent', 'throttle:identity'])->name('admin.offline-owner-recovery.rotate');
Route::post('/internal/admin/auth/offline-owner-recovery/redeem', [IdentityController::class, 'redeemOfflineOwnerCode'])->defaults('identity_realm', 'admin')->middleware(['identity', 'throttle:identity'])->name('admin.offline-owner-recovery.redeem');
Route::get('/internal/admin/pos/login', [PosShellController::class, 'login'])
    ->defaults('identity_realm', 'admin')->middleware('identity')->name('admin.pos.login');

Route::get('/internal/admin/forgot-password', [PosShellController::class, 'recoveryRequest'])->defaults('identity_realm', 'admin')->middleware('identity')->name('admin.recovery.request');
Route::get('/internal/admin/reset-password', [PosShellController::class, 'recoveryReset'])->defaults('identity_realm', 'admin')->middleware('identity')->name('admin.recovery.reset');
Route::prefix('/api/v1')->middleware(['identity', 'identity.auth', 'throttle:api-customer'])->group(function () {
    Route::post('/cart/quote', [CustomerApiController::class, 'cartQuote'])->defaults('identity_realm', 'customer')->name('api.customer.cart.quote');
    Route::get('/checkout/channels', [CustomerApiController::class, 'checkoutChannels'])->defaults('identity_realm', 'customer')->name('api.customer.checkout.channels');
    Route::post('/orders', [CustomerApiController::class, 'checkout'])->defaults('identity_realm', 'customer')->name('api.customer.orders.store');
    Route::get('/orders', [CustomerApiController::class, 'orders'])->defaults('identity_realm', 'customer')->name('api.customer.orders.index');
    Route::get('/orders/{order}', [CustomerApiController::class, 'order'])->defaults('identity_realm', 'customer')->name('api.customer.orders.show');
    Route::post('/orders/{order}/cancel', [CustomerApiController::class, 'cancel'])->defaults('identity_realm', 'customer')->name('api.customer.orders.cancel');
    Route::post('/orders/{order}/payments/retry', [CustomerApiController::class, 'retryPayment'])->defaults('identity_realm', 'customer')->name('api.customer.payments.retry');
    Route::post('/payments/{payment}/initiate', [CustomerApiController::class, 'initiatePayment'])->defaults('identity_realm', 'customer')->name('api.customer.payments.initiate');
    Route::post('/project-milestones/pay', [CustomerApiController::class, 'milestone'])->defaults('identity_realm', 'customer')->name('api.customer.milestones.pay');
    Route::get('/project-payment-channels', [CustomerApiController::class, 'projectPaymentChannels'])->defaults('identity_realm', 'customer')->name('api.customer.projects.payment-channels');
    Route::get('/projects', [CustomerApiController::class, 'projects'])->defaults('identity_realm', 'customer')->name('api.customer.projects.index');
    Route::get('/projects/{project}', [CustomerApiController::class, 'project'])->defaults('identity_realm', 'customer')->name('api.customer.projects.show');
    Route::post('/projects/{project}/files/reference', [CustomerApiController::class, 'projectReference'])->defaults('identity_realm', 'customer')->name('api.customer.projects.files.reference');
    Route::get('/projects/{project}/files/{file}', [CustomerApiController::class, 'projectFile'])->defaults('identity_realm', 'customer')->name('api.customer.projects.files');
    Route::get('/wishlist', [CustomerApiController::class, 'wishlist'])->defaults('identity_realm', 'customer')->name('api.customer.wishlist.index');
    Route::post('/wishlist/{product}', [CustomerApiController::class, 'saveWishlist'])->whereUuid('product')->defaults('identity_realm', 'customer')->name('api.customer.wishlist.store');
    Route::delete('/wishlist/{product}', [CustomerApiController::class, 'removeWishlist'])->whereUuid('product')->defaults('identity_realm', 'customer')->name('api.customer.wishlist.destroy');
    Route::post('/wishlist/claim', [CustomerApiController::class, 'claimWishlist'])->defaults('identity_realm', 'customer')->name('api.customer.wishlist.claim');
    Route::get('/notification-preferences', [CustomerApiController::class, 'notificationPreferences'])->defaults('identity_realm', 'customer')->name('api.customer.notifications.preferences');
    Route::put('/notification-preferences', [CustomerApiController::class, 'updateNotificationPreferences'])->defaults('identity_realm', 'customer')->name('api.customer.notifications.preferences.update');
    Route::get('/product-subscriptions', [CustomerApiController::class, 'subscriptions'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.index');
    Route::post('/product-subscriptions/{product}', [CustomerApiController::class, 'subscribe'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.store');
    Route::delete('/product-subscriptions/{product}', [CustomerApiController::class, 'unsubscribe'])->defaults('identity_realm', 'customer')->name('api.customer.subscriptions.destroy');
    Route::get('/reviews', [CustomerApiController::class, 'reviews'])->defaults('identity_realm', 'customer')->name('api.customer.reviews.index');
    Route::get('/reviews/eligible', [CustomerApiController::class, 'reviewEligibility'])->defaults('identity_realm', 'customer')->name('api.customer.reviews.eligible');
    Route::post('/reviews', [CustomerApiController::class, 'submitReview'])->defaults('identity_realm', 'customer')->name('api.customer.reviews.store');
    Route::get('/loyalty', [CustomerApiController::class, 'loyalty'])->defaults('identity_realm', 'customer')->name('api.customer.loyalty');
});
Route::prefix('/internal/admin')->middleware(['identity', 'identity.auth'])->group(function () {
    Route::get('/pos', [PosShellController::class, 'home'])->defaults('identity_realm', 'admin')->name('admin.pos.home');
    Route::get('/manage-account', [PosShellController::class, 'manageAccount'])->defaults('identity_realm', 'admin')->name('admin.account.page');
    Route::get('/manage-account/photo', [PosShellController::class, 'profilePhoto'])->defaults('identity_realm', 'admin')->name('admin.account.photo.show');
    Route::post('/manage-account/photo', [PosShellController::class, 'uploadProfilePhoto'])->defaults('identity_realm', 'admin')->middleware('throttle:identity')->name('admin.account.photo.upload');
    Route::delete('/manage-account/photo', [PosShellController::class, 'removeProfilePhoto'])->defaults('identity_realm', 'admin')->middleware('throttle:identity')->name('admin.account.photo.delete');
    Route::get('/pos/workspace/{area}', [PosShellController::class, 'workspace'])->whereIn('area', ['sales', 'inventory', 'invoices', 'warranty', 'claims', 'profile', 'reports', 'operations', 'master-data'])
        ->defaults('identity_realm', 'admin')->name('admin.pos.workspace');
    Route::get('/pos/outlet-profile', [PosShellController::class, 'outletProfile'])->defaults('identity_realm', 'admin')->name('admin.pos.outlet-profile.show');
    Route::patch('/pos/outlet-profile', [PosShellController::class, 'updateOutletProfile'])->defaults('identity_realm', 'admin')->name('admin.pos.outlet-profile.update');
    Route::get('/pos/master-data', [PosMasterDataController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.pos.master-data.index');
    Route::post('/pos/master-data', [PosMasterDataController::class, 'change'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.pos.master-data.change');
    Route::get('/pos/catalogue', [PosTransactionController::class, 'catalogue'])->defaults('identity_realm', 'admin')->name('admin.pos.catalogue');
    Route::get('/pos/lookup', [PosTransactionController::class, 'lookup'])->defaults('identity_realm', 'admin')->name('admin.pos.lookup');
    Route::post('/pos/inventory/products', [PosTransactionController::class, 'saveProduct'])->defaults('identity_realm', 'admin')->name('admin.pos.products.save');
    Route::post('/pos/inventory/products/{product}/website-listing', [PosTransactionController::class, 'websiteListing'])->defaults('identity_realm', 'admin')->name('admin.pos.products.website-listing');
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
    Route::get('/pos/customer-reporting/claims/sale-search', [PosCustomerReportingController::class, 'searchWarrantyIntake'])->defaults('identity_realm', 'admin')->name('admin.pos.claims.sale-search');
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
    Route::get('/digital-operations', [DigitalOperationsController::class, 'page'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.page');
    Route::get('/digital-operations/data', [DigitalOperationsController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.data');
    Route::post('/digital-operations/services', [DigitalOperationsController::class, 'service'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.services.save');
    Route::put('/digital-operations/consultation', [DigitalOperationsController::class, 'consultation'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.consultation.save');
    Route::get('/digital-operations/leads/{lead}', [DigitalOperationsController::class, 'lead'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.leads.show');
    Route::patch('/digital-operations/leads/{lead}', [DigitalOperationsController::class, 'leadUpdate'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.leads.update');
    Route::get('/digital-operations/leads/{lead}/files/{file}', [DigitalOperationsController::class, 'leadFile'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.leads.files');
    Route::post('/digital-operations/leads/{lead}/projects', [DigitalOperationsController::class, 'projectCreate'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.projects.create');
    Route::get('/digital-operations/projects/{project}', [DigitalOperationsController::class, 'project'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.projects.show');
    Route::patch('/digital-operations/projects/{project}', [DigitalOperationsController::class, 'projectTransition'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.projects.transition');
    Route::post('/digital-operations/projects/{project}/proposals', [DigitalOperationsController::class, 'proposal'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.proposals.create');
    Route::post('/digital-operations/proposals/{proposal}/approve', [DigitalOperationsController::class, 'approveProposal'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.proposals.approve');
    Route::post('/digital-operations/projects/{project}/files', [DigitalOperationsController::class, 'delivery'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.projects.files.upload');
    Route::get('/digital-operations/projects/{project}/files/{file}', [DigitalOperationsController::class, 'projectFile'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.projects.files.download');
    Route::get('/digital-operations/conversions', [DigitalOperationsController::class, 'conversions'])->defaults('identity_realm', 'admin')->name('admin.digital-operations.conversions');
    Route::get('/reset-administration', [ResetAdministrationController::class, 'page'])->defaults('identity_realm', 'admin')->name('admin.reset.page');
    Route::get('/reset-administration/data', [ResetAdministrationController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.reset.data');
    Route::post('/reset-administration/preview', [ResetAdministrationController::class, 'preview'])->defaults('identity_realm', 'admin')->name('admin.reset.preview');
    Route::post('/reset-administration/operations/{operation}/execute', [ResetAdministrationController::class, 'execute'])->defaults('identity_realm', 'admin')->name('admin.reset.execute');
    Route::get('/platform', [PlatformAdministrationController::class, 'page'])->defaults('identity_realm', 'admin')->name('admin.platform.page');
    Route::get('/platform/data', [PlatformAdministrationController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.platform.data');
    Route::post('/platform/pos-config/{domain}/preview', [PlatformAdministrationController::class, 'posConfigurationPreview'])->whereIn('domain', ['documents', 'theme', 'branding'])->defaults('identity_realm', 'admin')->name('admin.platform.pos-config.preview');
    Route::post('/platform/pos-config/{domain}/draft', [PlatformAdministrationController::class, 'posConfigurationDraft'])->whereIn('domain', ['documents', 'theme', 'branding'])->defaults('identity_realm', 'admin')->name('admin.platform.pos-config.draft');
    Route::post('/platform/pos-config/revisions/{revision}/publish', [PlatformAdministrationController::class, 'posConfigurationPublish'])->defaults('identity_realm', 'admin')->name('admin.platform.pos-config.publish');
    Route::post('/platform/pos-config/revisions/{revision}/rollback', [PlatformAdministrationController::class, 'posConfigurationRollback'])->defaults('identity_realm', 'admin')->name('admin.platform.pos-config.rollback');
    Route::post('/platform/pos-config/branding/media', [PlatformAdministrationController::class, 'posBrandingMedia'])->defaults('identity_realm', 'admin')->name('admin.platform.pos-config.branding.media');
    Route::delete('/platform/pos-config/branding/media/{media}', [PlatformAdministrationController::class, 'posBrandingMediaDelete'])->defaults('identity_realm', 'admin')->name('admin.platform.pos-config.branding.media.delete');
    Route::patch('/platform/business-profile', [PlatformAdministrationController::class, 'businessProfile'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.platform.business-profile');
    Route::post('/platform/website-mode/draft', [PlatformAdministrationController::class, 'modeDraft'])->defaults('identity_realm', 'admin')->name('admin.platform.mode.draft');
    Route::get('/platform/website-mode/{revision}/preview', [PlatformAdministrationController::class, 'modePreview'])->defaults('identity_realm', 'admin')->name('admin.platform.mode.preview');
    Route::post('/platform/website-mode/{revision}/publish', [PlatformAdministrationController::class, 'modePublish'])->defaults('identity_realm', 'admin')->name('admin.platform.mode.publish');
    Route::post('/platform/website-mode/{revision}/rollback', [PlatformAdministrationController::class, 'modeRollback'])->defaults('identity_realm', 'admin')->name('admin.platform.mode.rollback');
    Route::post('/platform/presentation/draft', [PlatformAdministrationController::class, 'presentationDraft'])->defaults('identity_realm', 'admin')->name('admin.platform.presentation.draft');
    Route::post('/platform/presentation/{revision}/publish', [PlatformAdministrationController::class, 'presentationPublish'])->defaults('identity_realm', 'admin')->name('admin.platform.presentation.publish');
    Route::post('/platform/presentation/{revision}/rollback', [PlatformAdministrationController::class, 'presentationRollback'])->defaults('identity_realm', 'admin')->name('admin.platform.presentation.rollback');
    Route::post('/platform/pages/draft', [PlatformAdministrationController::class, 'pageDraft'])->defaults('identity_realm', 'admin')->name('admin.platform.pages.draft');
    Route::post('/platform/pages/{revision}/publish', [PlatformAdministrationController::class, 'pagePublish'])->defaults('identity_realm', 'admin')->name('admin.platform.pages.publish');
    Route::post('/platform/pages/{revision}/rollback', [PlatformAdministrationController::class, 'pageRollback'])->defaults('identity_realm', 'admin')->name('admin.platform.pages.rollback');
    Route::post('/platform/media', [PlatformAdministrationController::class, 'mediaRegister'])->defaults('identity_realm', 'admin')->name('admin.platform.media.register');
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
    Route::get('/pos/audit', [PosAuditController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.pos.audit');
    Route::get('/pos/portal-preferences', [PosPortalPreferencesController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.pos.preferences');
    Route::put('/pos/portal-preferences', [PosPortalPreferencesController::class, 'update'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.pos.preferences.update');
    Route::get('/pos/dashboard-report-preferences', [PosDashboardReportPreferencesController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.pos.dashboard-report-preferences');
    Route::put('/pos/dashboard-report-preferences', [PosDashboardReportPreferencesController::class, 'update'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.pos.dashboard-report-preferences.update');
    Route::get('/outlet-management', [OutletManagementController::class, 'page'])->defaults('identity_realm', 'admin')->name('admin.outlets.page');
    Route::get('/outlet-management/data', [OutletManagementController::class, 'index'])->defaults('identity_realm', 'admin')->name('admin.outlets.index');
    Route::get('/outlet-management/{outlet}/profile', [OutletManagementController::class, 'profile'])->whereUuid('outlet')->defaults('identity_realm', 'admin')->name('admin.outlets.profile.show');
    Route::patch('/outlet-management/{outlet}/profile', [OutletManagementController::class, 'updateProfile'])->whereUuid('outlet')->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.outlets.profile.update');
    Route::post('/outlet-management', [OutletManagementController::class, 'store'])->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.outlets.create');
    Route::post('/outlet-management/{outlet}/history/claims/{claim}/retrieve', [OutletManagementController::class, 'archivedClaimDocument'])->whereUuid('outlet')->whereUuid('claim')->defaults('identity_realm', 'admin')->middleware(['throttle:identity'])->name('admin.outlets.archived-claim.retrieve');
    Route::post('/outlet-management/{outlet}/history/invoices/{invoice}/retrieve', [OutletManagementController::class, 'archivedInvoiceDocument'])->whereUuid('outlet')->whereUuid('invoice')->defaults('identity_realm', 'admin')->middleware(['throttle:identity'])->name('admin.outlets.archived-invoice.retrieve');
    Route::post('/outlet-management/{outlet}/history/invoices/{invoice}/reconstructed-pdf', [OutletManagementController::class, 'archivedInvoicePdf'])->whereUuid('outlet')->whereUuid('invoice')->defaults('identity_realm', 'admin')->middleware(['throttle:identity'])->name('admin.outlets.archived-invoice.reconstructed-pdf');
    Route::get('/outlet-management/{outlet}/history', [OutletManagementController::class, 'archivedHistory'])->whereUuid('outlet')->defaults('identity_realm', 'admin')->name('admin.outlets.archived-history');
    Route::post('/outlet-management/{outlet}/archive', [OutletManagementController::class, 'archive'])->whereUuid('outlet')->defaults('identity_realm', 'admin')->middleware(['throttle:identity', 'identity.recent'])->name('admin.outlets.archive');
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
