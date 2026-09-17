import { expect, Page, test } from '@playwright/test';

const password = 'SyntheticPass123!';

async function login(page: Page, email: string) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

test('sales UI completes split tender then exposes accepted-return refund controls', async ({ page }) => {
    const productId = '11111111-1111-4111-8111-111111111111';
    const cashId = '22222222-2222-4222-8222-222222222222';
    const cardId = '33333333-3333-4333-8333-333333333333';
    const invoiceId = '44444444-4444-4444-8444-444444444444';
    const cashTenderId = '55555555-5555-4555-8555-555555555555';
    const cardTenderId = '66666666-6666-4666-8666-666666666666';
    const saleId = '77777777-7777-4777-8777-777777777777';
    const returnId = '88888888-8888-4888-8888-888888888888';

    await page.route('**/internal/admin/pos/catalogue**', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            outlet: { id: 'e2e-sales', name: 'E2E Sales Outlet' },
            products: [{ id: productId, code: 'E2E-MT42', name: 'E2E MT42 Accessory', purchase_price: '100.00',
                sale_price: '200.02', qty: 4, track_imei: false, units: [] }],
            page: 1, has_more: false, master_data: [],
            payment_destinations: [
                { public_id: cashId, method: 'cash', display_name: 'E2E Cash Drawer' },
                { public_id: cardId, method: 'card', display_name: 'E2E Card Terminal' },
            ],
        } }) });
    });
    await page.route('**/internal/admin/pos/sales/quote', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            gross: '200.02', discount: '0.00', payable: '200.02', payments_total: '200.02',
            remaining: '0.00', cash_change: '50.00', promotion_or_loyalty_recalculated_on_finalize: false,
        } }) });
    });
    await page.route('**/internal/admin/pos/sales', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            invoice_id: invoiceId, invoice_number: 'INV-E2E-42', total_bill: '200.02', discount: '0.00',
            final_bill: '200.02', sale_ids: [saleId], paid_amount: '200.02', remaining: '0.00',
            cash_change_total: '50.00', payments: [
                { allocation_id: cashTenderId, method: 'cash', destination_id: cashId, amount: '50.02' },
                { allocation_id: cardTenderId, method: 'card', destination_id: cardId, amount: '150.00' },
            ],
        } }) });
    });
    await page.route('**/internal/admin/pos/invoices/**', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            invoice_id: invoiceId, invoice_number: 'INV-E2E-42', final_bill: '200.02', currency: 'PKR',
            payments: [
                { allocation_id: cashTenderId, method: 'cash', destination_id: cashId, destination_name: 'E2E Cash Drawer', amount: '50.02' },
                { allocation_id: cardTenderId, method: 'card', destination_id: cardId, destination_name: 'E2E Card Terminal', amount: '150.00' },
            ],
            refunds: [], lines: [{ sale_id: saleId, product_id: productId, name: 'E2E MT42 Accessory',
                quantity: 1, returned_quantity: 0, sale_price: '200.02', net_total_price: '200.02', track_imei: false, units: [] }],
        } }) });
    });
    await page.route('**/internal/admin/pos/returns', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            return_id: returnId, status: 'accepted', refund_due: '200.02', currency: 'PKR', refund_status: 'not_created',
        } }) });
    });
    await page.route('**/internal/admin/pos/refunds', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            refund_id: '99999999-9999-4999-8999-999999999999', return_id: returnId, amount: '50.02',
            currency: 'PKR', original_method: 'cash', refund_method: 'cash', refund_destination_id: cashId, override: false,
        } }) });
    });

    await login(page, 'e2e-sales@example.invalid');
    await page.getByRole('link', { name: 'Sales', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/sales');

    await page.getByRole('button', { name: /E2E MT42 Accessory/ }).click();
    await page.getByRole('button', { name: 'Add Payment' }).click();
    await page.getByRole('button', { name: 'Add Payment' }).click();

    const salePanel = page.getByRole('heading', { name: 'Sale & payment' }).locator('..');
    await salePanel.locator('select').nth(1).selectOption(cardId);
    await salePanel.getByPlaceholder('Amount').nth(0).fill('50.02');
    await salePanel.getByPlaceholder('Amount').nth(1).fill('150.00');
    await salePanel.getByPlaceholder('Cash tendered').fill('100.02');
    await salePanel.getByPlaceholder('Safe reference').nth(1).fill('E2E-APP-42');

    await page.getByTestId('server-totals').click();
    await expect(page.getByTestId('authoritative-totals')).toContainText('PKR 200.02');
    await expect(page.getByTestId('authoritative-totals')).toContainText('PKR 50.00');
    await expect(page.getByTestId('finalize-sale')).toBeEnabled();
    await page.getByTestId('finalize-sale').click();
    await expect(page.getByTestId('sale-result')).toContainText('INV-E2E-42');

    const returnPanel = page.getByRole('heading', { name: 'Return / refund' }).locator('..');
    await returnPanel.getByPlaceholder('Invoice public ID').fill(invoiceId);
    await returnPanel.getByRole('button', { name: 'Load invoice' }).click();
    await returnPanel.locator('select').first().selectOption(saleId);
    await returnPanel.getByRole('button', { name: 'Accept return' }).click();
    await expect(returnPanel).toContainText('Refund due: PKR 200.02');
    await expect(returnPanel.getByText('E2E Cash Drawer · PKR 50.02')).toBeVisible();
    await expect(returnPanel.getByText('E2E Card Terminal · PKR 150.00')).toBeVisible();
    await returnPanel.getByPlaceholder('Refund amount').fill('50.02');
    await returnPanel.getByRole('button', { name: 'Record refund' }).click();
    await expect(page.getByTestId('refund-result')).toContainText('PKR 50.02 via cash');
});
