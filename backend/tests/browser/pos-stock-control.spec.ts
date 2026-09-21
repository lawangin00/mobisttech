import { expect, Page, test } from '@playwright/test';
import { releaseSyntheticAdminSession } from './synthetic-admin-session';

test.afterEach(async ({ page }) => {
    await releaseSyntheticAdminSession(page);
});

const password = 'SyntheticPass123!';

async function loginInventory(page: Page) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-inventory@example.invalid');
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    const selected = page.waitForResponse((response) =>
        response.url().endsWith('/internal/admin/outlets/select')
        && response.request().method() === 'POST'
        && response.ok()
    );
    await page.getByTestId('outlet-select').selectOption({ label: 'E2E Inventory Outlet' });
    await selected;
    await expect(page.getByTestId('outlet-required')).toHaveCount(0);
}

test('stock control UI exposes permission-scoped procurement count transfer and bulk journeys', async ({ page }) => {
    const productId = '11111111-1111-4111-8111-111111111111';
    const destinationProductId = '22222222-2222-4222-8222-222222222222';
    const supplierId = '33333333-3333-4333-8333-333333333333';
    const orderId = '44444444-4444-4444-8444-444444444444';
    const orderLineId = '55555555-5555-4555-8555-555555555555';
    const stocktakeId = '66666666-6666-4666-8666-666666666666';
    const stocktakeLineId = '77777777-7777-4777-8777-777777777777';
    const transferId = '88888888-8888-4888-8888-888888888888';
    const transferLineId = '99999999-9999-4999-8999-999999999999';
    const destinationOutletId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    let transferStatus = 'draft';
    let transferVersion = 1;

    const data = () => ({
        outlet: { id: 'inventory-outlet', name: 'E2E Inventory Outlet' },
        permissions: { canProcure: true, canStocktake: true, canApprove: true, canDispatch: true, canReceive: true, canBulk: true },
        products: [{ id: productId, name: 'E2E Stock Product', code: 'E2E-45', qty: 3, track_imei: false, reorder_policy: null }],
        outlets: [{ id: 'inventory-outlet', name: 'E2E Inventory Outlet' }, { id: destinationOutletId, name: 'E2E Destination' }],
        products_by_outlet: { [destinationOutletId]: [{ id: destinationProductId, name: 'E2E Stock Product', code: 'E2E-45-D', qty: 0, track_imei: false }] },
        suppliers: [{ public_id: supplierId, supplier_code: 'SUP45', name: 'E2E Supplier', is_active: true, version: 1 }],
        orders: [{ id: orderId, number: 'PO-E2E-45', supplier_name: 'E2E Supplier', status: 'ordered', lines: [{ line_id: orderLineId, product_id: productId, name: 'E2E Stock Product', ordered_quantity: 4, received_quantity: 1, ordered_unit_cost: '100.00', planned_landed_unit_cost: '105.00' }] }],
        stocktakes: [{ public_id: stocktakeId, session_number: 'ST-E2E-45', kind: 'cycle', status: 'counting', version: 1 }],
        transfers: [{ public_id: transferId, transfer_number: 'TR-E2E-45', status: transferStatus, version: transferVersion, source_outlet_id: 'inventory-outlet', source_outlet_name: 'E2E Inventory Outlet', destination_outlet_id: destinationOutletId, destination_outlet_name: 'E2E Destination' }],
        recommendations: [{ product_id: productId, name: 'E2E Stock Product', stock_status: 'low_stock', available: 1, incoming: 1, reorder_threshold: 2, target_stock: 6, recommended_quantity: 4, action: 'create_purchase_order' }],
    });

    await page.route('**/internal/admin/pos/stock-control', async (route) => {
        if (route.request().method() === 'GET') {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: data() }) });
            return;
        }
        await route.fallback();
    });
    await page.route('**/internal/admin/pos/stock-control/suppliers', async (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { supplier_id: supplierId, version: 1 } }) }));
    await page.route('**/internal/admin/pos/stock-control/orders', async (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { purchase_order_id: orderId, order_number: 'PO-E2E-45', status: 'ordered' } }) }));
    await page.route('**/internal/admin/pos/stock-control/orders/*/receive', async (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { purchase_order_id: orderId, status: 'partially_received' } }) }));
    await page.route('**/internal/admin/pos/stock-control/reorder/*', async (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { product_id: productId, version: 1 } }) }));
    await page.route('**/internal/admin/pos/stock-control/stocktakes', async (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { stocktake_id: stocktakeId, status: 'counting', version: 1 } }) }));
    await page.route('**/internal/admin/pos/stock-control/stocktakes/*', async (route) => {
        const url = route.request().url();
        if (route.request().method() === 'GET') {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { stocktake_id: stocktakeId, session_number: 'ST-E2E-45', kind: 'cycle', status: 'counting', version: 1, lines: [{ line_id: stocktakeLineId, product_id: productId, product_name: 'E2E Stock Product', tracked_serialized: false, baseline_quantity: 3, iteration: 1, expected_quantity: null, counted_quantity: null, variance: null, reason_code: null, line_version: 1 }] } }) });
        } else {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { stocktake_id: stocktakeId, status: url.endsWith('/approve') ? 'approved' : 'submitted', session_version: 2, variance: 0 } }) });
        }
    });
    await page.route('**/internal/admin/pos/stock-control/transfers', async (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { transfer_id: transferId, transfer_number: 'TR-E2E-45', status: 'draft', version: 1 } }) }));
    await page.route('**/internal/admin/pos/stock-control/transfers/**', async (route) => {
        if (route.request().method() === 'GET') {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { transfer_id: transferId, transfer_number: 'TR-E2E-45', source_outlet_id: 'inventory-outlet', destination_outlet_id: destinationOutletId, status: transferStatus, version: transferVersion, lines: [{ line_id: transferLineId, source_product_id: productId, destination_product_id: destinationProductId, tracked_serialized: false, quantity: 2, received_quantity: 0, rejected_quantity: 0, version: 1, units: [] }] } }) });
        } else {
            if (route.request().url().endsWith('/dispatch')) { transferStatus = 'in_transit'; transferVersion = 2; }
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { transfer_id: transferId, status: transferStatus, version: transferVersion, remaining_quantity: 2 } }) });
        }
    });
    await page.route('**/internal/admin/pos/stock-control/bulk/preview', async (route) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { dataset: 'inventory', format: 'csv', valid: 1, invalid: 1, rows: [{ row: 2, status: 'valid' }, { row: 3, status: 'invalid', errors: { quantity: ['Invalid quantity'] } }] } }) }));

    await loginInventory(page);
    await page.getByRole('link', { name: 'Inventory', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/inventory');
    await expect(page.getByTestId('stock-control')).toBeVisible();
    await expect(page.getByText('Suppliers & purchase orders')).toBeVisible();
    await expect(page.getByText('Stocktake / recount / approval')).toBeVisible();
    await expect(page.getByText('Inter-outlet transfers')).toBeVisible();
    await expect(page.getByText('Validated bulk operations')).toBeVisible();

    await page.getByPlaceholder('Supplier code').fill('NEW45');
    await page.getByPlaceholder('Supplier name').fill('New supplier');
    await page.getByTestId('supplier-create').click();

    const procurement = page.getByRole('heading', { name: 'Suppliers & purchase orders' }).locator('..');
    await procurement.locator('select').nth(0).selectOption(supplierId);
    await procurement.locator('select').nth(1).selectOption(productId);
    await page.getByTestId('po-create').click();
    await procurement.locator('select').nth(2).selectOption(orderId);
    await procurement.locator('select').nth(3).selectOption(orderLineId);
    await page.getByTestId('po-receive').click();

    const stocktake = page.getByRole('heading', { name: 'Stocktake / recount / approval' }).locator('..');
    await stocktake.locator('select').nth(1).selectOption(productId);
    await page.getByTestId('stocktake-start').click();
    await stocktake.getByPlaceholder('Counted quantity').fill('3');
    await stocktake.getByRole('button', { name: 'Submit count' }).click();

    const transfer = page.getByRole('heading', { name: 'Inter-outlet transfers' }).locator('..');
    await transfer.locator('select').nth(0).selectOption(destinationOutletId);
    await transfer.locator('select').nth(1).selectOption(productId);
    await transfer.locator('select').nth(2).selectOption(destinationProductId);
    await page.getByTestId('transfer-create').click();
    await transfer.getByRole('button', { name: 'Dispatch' }).click();
    await expect(transfer).toContainText('in_transit');

    const bulk = page.getByRole('heading', { name: 'Validated bulk operations' }).locator('..');
    await bulk.locator('textarea').fill('row_key,product_id,action,quantity,reason,type\nrow-1,' + productId + ',adjust,1,Check,correction_in');
    await page.getByTestId('bulk-preview').click();
    await expect(page.getByTestId('bulk-result')).toContainText('"valid": 1');
    await expect(page.getByTestId('bulk-result')).toContainText('"invalid": 1');

    await page.setViewportSize({ width: 390, height: 844 });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

});
