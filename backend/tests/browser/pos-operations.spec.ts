import { expect, Page, test } from '@playwright/test';

const password = 'SyntheticPass123!';
const cashSessionId = '11111111-1111-4111-8111-111111111111';
const cashEntryId = '22222222-2222-4222-8222-222222222222';
const allocationId = '33333333-3333-4333-8333-333333333333';
const productId = '44444444-4444-4444-8444-444444444444';
const tradeId = '55555555-5555-4555-8555-555555555555';
const repairId = '66666666-6666-4666-8666-666666666666';
const estimateId = '77777777-7777-4777-8777-777777777777';
const destinationId = '88888888-8888-4888-8888-888888888888';

async function login(page: Page) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-operations@example.invalid');
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

test('operations UI covers cash settlement trade-in repair history and mobile containment', async ({ page }) => {
    let entryStatus = 'pending';
    let settlementState = 'pending';
    let tradeStatus = 'pending';
    let repairEnabled = false;
    let repairStatus = 'awaiting_approval';
    let approvedEstimateId: string | null = null;
    let repairPaid = false;
    let cashOpen = true;

    const repairDetail = () => ({
        repair_id: repairId,
        repair_number: 'RPR-E2E-46',
        version: 3,
        customer_name: 'E2E Repair Customer',
        customer_phone: '03005556666',
        device_label: 'E2E Device',
        identifier_type: 'serial',
        identifier_value: 'SERIAL-E2E-46',
        issue_description: 'E2E paid repair issue',
        received_condition: 'Used',
        accessories_received: 'Handset only',
        diagnosis: 'Charging flex issue',
        internal_notes: null,
        status: repairStatus,
        approved_estimate_id: approvedEstimateId,
        estimates: [{
            estimate_id: estimateId,
            version: 1,
            status: approvedEstimateId ? 'approved' : 'proposed',
            parts_total: '0.00',
            labor_total: '50.00',
            grand_total: '50.00',
            currency: 'PKR',
            notes: 'E2E estimate',
            lines: [{ type: 'labor', product_id: null, description: 'Repair labor', quantity: 1, unit_price: '50.00', line_total: '50.00' }],
        }],
        payments: repairPaid ? [{ allocation_id: '99999999-9999-4999-8999-999999999999', method: 'bank_transfer', amount: '50.00', reconciliation_state: 'pending' }] : [],
        events: [
            { event_type: 'opened', status: 'received', occurred_at: '2026-09-18T07:00:00Z' },
            { event_type: 'estimate_proposed', status: 'awaiting_approval', occurred_at: '2026-09-18T07:05:00Z' },
        ],
    });

    const data = () => ({
        outlet: { id: 'sales-outlet', name: 'E2E Sales Outlet' },
        permissions: { canCash: true, canCashApprove: true, canTradeIn: true, canRepairs: true, canReconcile: true },
        cash_session: cashOpen ? {
            session_id: cashSessionId,
            business_date: '2026-09-18',
            status: 'open',
            version: entryStatus === 'pending' ? 2 : 3,
            opening_cash: '100.00',
            summary: {
                cash_sales: '0.00',
                approved_cash_in: '0.00',
                cash_refunds: '0.00',
                expenses: entryStatus === 'approved' ? '10.00' : '0.00',
                payouts: '0.00',
                expected_cash: entryStatus === 'approved' ? '90.00' : '100.00',
                non_cash_destinations: [{
                    destination_id: destinationId,
                    method: 'card',
                    display_name: 'E2E Card Terminal',
                    gross_expected_receipts: '200.02',
                    allocation_count: 1,
                    settled_allocation_count: settlementState === 'confirmed' ? 1 : 0,
                    merchant_fees: settlementState === 'confirmed' ? '3.00' : '0.00',
                    adjustments: settlementState === 'confirmed' ? '-0.50' : '0.00',
                    expected_net: settlementState === 'confirmed' ? '196.52' : '0.00',
                    received_net: settlementState === 'confirmed' ? '196.52' : '0.00',
                    settlement_variance: '0.00',
                }],
            },
            entries: [{ entry_id: cashEntryId, type: 'expense', amount: '10.00', reason: 'E2E expense', reference: 'EXP-46', status: entryStatus }],
        } : null,
        cash_history: [{ session_id: cashSessionId, business_date: '2026-09-18', status: cashOpen ? 'open' : 'closed', opening_cash: '100.00', expected_cash: '90.00', actual_cash: cashOpen ? null : '90.00', variance_amount: cashOpen ? null : '0.00' }],
        settlements: [{
            allocation_id: allocationId,
            method: 'card',
            amount: '200.02',
            destination_name: 'E2E Card Terminal',
            reconciliation_state: settlementState,
            settlement_version: settlementState === 'confirmed' ? 1 : 0,
            latest: settlementState === 'confirmed' ? {
                fee_amount: '3.00',
                adjustment_amount: '-0.50',
                expected_net_amount: '196.52',
                received_net_amount: '196.52',
                variance_amount: '0.00',
                recorded_at: '2026-09-18T07:10:00Z',
            } : null,
        }],
        serialized_products: [{ id: productId, name: 'E2E Serialized Phone', code: 'E2E-46', required_imei_slots: 2 }],
        trade_ins: [{
            trade_in_id: tradeId,
            status: tradeStatus,
            version: tradeStatus === 'pending' ? 1 : 2,
            product_id: productId,
            settlement_mode: 'purchase',
            valuation_amount: '100.00',
            invoice_id: null,
            device_serial: 'SER-E2E-46',
            condition: 'Used - inspected',
            imeis: ['352099001761466', '352099001761474'],
            seller: { name: 'E2E Seller', cnic: '*****-*******-1', phone: '0300*****67' },
        }],
        invoice_candidates: [],
        repair_setting: { enabled: repairEnabled, version: 1 },
        repairs: [{ id: repairId, number: 'RPR-E2E-46', customer_name: 'E2E Repair Customer', customer_phone: '0300*****66', device_label: 'E2E Device', identifier_type: 'serial', identifier_value: '*********E-46', status: repairStatus, version: 3 }],
        repair_parts: [],
        payment_destinations: [{ public_id: destinationId, method: 'bank_transfer', display_name: 'E2E Repair Account', provider_label: null, masked_identifier: '****0046' }],
    });

    await page.route('**/internal/admin/pos/operations', async (route) => {
        if (route.request().method() === 'GET') {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: data() }) });
            return;
        }
        await route.fallback();
    });
    await page.route('**/internal/admin/pos/operations/**', async (route) => {
        const url = route.request().url();
        const method = route.request().method();
        if (method === 'GET' && url.includes('/repairs/')) {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: repairDetail() }) });
            return;
        }
        if (method === 'GET' && url.includes('/trade-ins/')) {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { ...data().trade_ins[0], events: [{ sequence: 1, event_type: 'created', snapshot: { status: 'pending' }, created_at: '2026-09-18T07:00:00Z' }] } }) });
            return;
        }
        if (url.endsWith('/review')) {
            entryStatus = 'approved';
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { entry_id: cashEntryId, status: 'approved' } }) });
            return;
        }
        if (url.endsWith('/close')) {
            cashOpen = false;
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { session_id: cashSessionId, status: 'closed', version: 4, opening_cash: '100.00', closing: { expected_cash: '90.00', actual_cash: '90.00', variance_amount: '0.00' } } }) });
            return;
        }
        if (url.includes('/settlements/')) {
            settlementState = 'confirmed';
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { allocation_id: allocationId, state: 'confirmed', settlement_version: 1, gross_amount: '200.02', fee_amount: '3.00', adjustment_amount: '-0.50', expected_net_amount: '196.52', received_net_amount: '196.52', variance_amount: '0.00', currency: 'PKR' } }) });
            return;
        }
        if (url.endsWith('/approve') && url.includes('/trade-ins/')) {
            tradeStatus = 'approved';
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { ...data().trade_ins[0], status: 'approved', version: 2 } }) });
            return;
        }
        if (url.endsWith('/repairs/configure')) {
            repairEnabled = !repairEnabled;
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { enabled: repairEnabled, version: 2 } }) });
            return;
        }
        if (url.includes('/estimate/') && url.endsWith('/decision')) {
            approvedEstimateId = estimateId;
            repairStatus = 'approved';
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: repairDetail() }) });
            return;
        }
        if (url.endsWith('/collect')) {
            repairPaid = true;
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { repair_id: repairId, invoice_id: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', paid_amount: '50.00', remaining: '0.00', currency: 'PKR', payments: [] } }) });
            return;
        }
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) });
    });

    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);
    await expect(page.getByRole('link', { name: 'Operations', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sales', exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Inventory', exact: true })).toHaveCount(0);

    await page.getByRole('link', { name: 'Operations', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/operations');
    await expect(page.getByTestId('operations-workspace')).toBeVisible();
    await expect(page.getByText('Cash & Day Closing')).toBeVisible();
    await expect(page.getByText('Provider settlement reconciliation')).toBeVisible();
    await expect(page.getByText('Individual seller trade-in')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Paid repairs' })).toBeVisible();

    const cash = page.getByRole('heading', { name: 'Cash & Day Closing' }).locator('xpath=ancestor::section[1]');
    await expect(cash).toContainText('PKR 100.00');
    await cash.getByRole('button', { name: 'Approve' }).click();
    await expect(cash).toContainText('approved');
    await cash.getByPlaceholder('Actual cash count').fill('90.00');

    const settlement = page.getByRole('heading', { name: 'Provider settlement reconciliation' }).locator('..');
    await settlement.locator('select').selectOption(allocationId);
    await settlement.getByPlaceholder('Fee').fill('3.00');
    await settlement.getByPlaceholder('Adjustment +/-').fill('-0.50');
    await settlement.getByPlaceholder('Received net').fill('196.52');
    await page.getByTestId('settlement-save').click();
    await expect(settlement).toContainText('State: confirmed');

    const trade = page.getByRole('heading', { name: 'Individual seller trade-in' }).locator('..');
    await trade.locator('select').last().selectOption(tradeId);
    await expect(trade).toContainText('*****-*******-1');
    await trade.getByRole('button', { name: 'Approve valuation' }).click();
    await expect(trade).toContainText('approved');

    const repair = page.getByRole('heading', { name: 'Paid repairs' }).locator('xpath=ancestor::section[1]');
    await expect(page.getByTestId('repair-open')).toBeDisabled();
    await repair.locator('select').nth(1).selectOption(repairId);
    await expect(repair).toContainText('RPR-E2E-46');
    await expect(repair.getByRole('button', { name: 'Approve estimate' })).toBeVisible();
    await repair.getByRole('button', { name: 'Approve estimate' }).click();
    await repair.getByRole('button', { name: 'Add payment' }).click();
    await repair.getByPlaceholder('Amount').last().fill('50.00');
    await page.getByTestId('repair-collect').click();
    await expect(repair).toContainText('Collected: PKR 50.00');
    await expect(repair.getByText('Linked repair history / audit')).toBeVisible();

    await page.setViewportSize({ width: 390, height: 844 });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    await page.getByTestId('logout').click();
    await page.waitForURL('**/internal/admin/pos/login');
});
