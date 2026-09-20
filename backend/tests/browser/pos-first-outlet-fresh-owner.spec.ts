import { expect, test } from '@playwright/test';

const email = 'mt75-fresh-owner@example.invalid';
const password = 'SyntheticFreshOwner123!';

test('fresh protected owner creates, configures and explicitly enters the first outlet', async ({ page }) => {
    test.setTimeout(125_000);
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');

    await expect(page.getByTestId('member-name')).toHaveText('MT75 Fresh Protected Owner');
    await expect(page.getByTestId('outlet-select')).toHaveCount(0);
    await expect(page.getByTestId('no-pos-access')).toBeVisible();
    await expect(page.getByTestId('nav-inventory')).toHaveCount(0);
    const denied = await page.request.get('/internal/admin/pos/workspace/inventory');
    expect(denied.status()).toBe(403);

    await page.getByTestId('manage-outlets-link').click();
    await page.waitForURL('**/internal/admin/outlet-management');
    await expect(page.getByText('001 · MT75 Fresh First Outlet')).toHaveCount(0);
    await page.getByTestId('outlet-owner-password').fill(password);
    await page.getByTestId('outlet-new-name').fill('MT75 Fresh First Outlet');
    await page.getByTestId('outlet-new-address').fill('Target-only synthetic address');
    const created = page.waitForResponse(response => response.url().endsWith('/internal/admin/outlet-management')
        && response.request().method() === 'POST');
    await page.getByTestId('outlet-create').click();
    expect((await created).status()).toBe(201);
    await expect(page.getByText('001 · MT75 Fresh First Outlet')).toBeVisible();

    await page.getByTestId('outlet-manage-profile-001').click();
    await expect(page.getByTestId('outlet-profile-editor')).toBeVisible();
    await page.getByTestId('outlet-profile-business_legal_name').fill('MT75 Fresh Legal Identity');
    await page.getByTestId('outlet-profile-business_phone').fill('+92 300 0000000');
    await page.getByTestId('outlet-profile-business_hours').fill('Mon-Sat 10:00-20:00');
    await page.getByTestId('outlet-managed-password').fill(password);
    await page.getByTestId('outlet-profile-save').click();
    await expect(page.getByTestId('outlet-profile-message')).toHaveText('Outlet profile saved.');
    await expect(page.getByTestId('outlet-profile-code')).toHaveText('001');

    await page.getByRole('link', { name: 'Back to POS' }).click();
    await page.waitForURL('**/internal/admin/pos');
    await expect(page.getByTestId('outlet-select')).toHaveValue('');
    await page.getByTestId('outlet-select').selectOption({ label: 'MT75 Fresh First Outlet' });
    await page.waitForURL('**/internal/admin/pos');
    await expect(page.getByTestId('outlet-select')).toHaveValue(/.+/);
    await page.getByRole('link', { name: 'Inventory', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/inventory');
    await expect(page.getByTestId('workspace-inventory')).toBeVisible();
    await expect(page.getByTestId('outlet-profile-code')).toHaveCount(0);

    await page.getByPlaceholder('Product name').fill('MT75 Fresh Accessory');
    await page.getByTestId('product-category').selectOption({ label: 'Accessories' });
    await page.getByPlaceholder('Purchase price').fill('100.00');
    await page.getByPlaceholder('Sale price').fill('150.00');
    await page.getByRole('combobox', { name: 'Warranty type' }).selectOption('shop_warranty');
    await page.getByRole('spinbutton', { name: 'Warranty duration' }).fill('30');
    await page.getByTestId('product-save').click();
    const product = page.getByRole('button', { name: /MT75 Fresh Accessory/ });
    await expect(product).toContainText('Qty 0');

    const receive = page.getByRole('heading', { name: 'Receive stock' }).locator('xpath=ancestor::section[1]');
    await receive.locator('select').nth(0).selectOption({ label: 'MT75 Fresh Accessory' });
    await receive.getByPlaceholder('Qty').fill('3');
    await receive.getByPlaceholder('Unit cost').fill('100.00');
    await receive.locator('select').nth(1).selectOption({ label: 'Supplier' });
    await receive.getByPlaceholder('Business / seller name').fill('MT75 Synthetic Supplier');
    await receive.getByPlaceholder('03XXXXXXXXX').fill('03000000000');
    await receive.getByPlaceholder('Address').fill('Target-only supplier address');
    await page.getByTestId('acquire-submit').click();
    await expect(page.getByRole('button', { name: /MT75 Fresh Accessory/ })).toContainText('Qty 3');
    await page.goto('/internal/admin/platform');
    const modeSection = page.getByRole('heading', { name: 'Website operating mode' }).locator('xpath=ancestor::section[1]');
    await modeSection.getByRole('button', { name: 'Save mode draft' }).click();
    await modeSection.getByRole('button', { name: 'Publish', exact: true }).first().click();
    await expect(modeSection).toContainText('Current: hybrid');
    await page.goto('/internal/admin/pos/workspace/inventory');
    await page.getByRole('button', { name: 'Website listing' }).click();
    await expect(page.getByTestId('website-listing-editor')).toBeVisible();
    await page.getByTestId('website-listing-publish').click();
    await expect(page.getByTestId('website-listing-editor')).toHaveCount(0);
    const publicProduct = await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products/mt75-fresh-accessory');
    expect(publicProduct.status()).toBe(200);
    const publicData = await publicProduct.json() as { data: { name: string; price: string; availability: {quantity:number} } };
    expect(publicData.data).toMatchObject({name:'MT75 Fresh Accessory',price:'150.00',availability:{quantity:3}});
    expect(JSON.stringify(publicData.data)).not.toMatch(/purchase_price|customer_phone|customer_email|imei|object_key/);

    await page.goto('/internal/admin/platform');
    await page.getByRole('button', { name: 'Documents, payments & retail' }).click();
    const destinations = page.getByRole('heading', { name: 'POS Payment Destinations' }).locator('xpath=ancestor::section[1]');
    await destinations.getByPlaceholder('Display name').fill('MT75 Fresh Bank');
    await destinations.getByPlaceholder('Masked identifier').fill('****7500');
    const destinationCreated = page.waitForResponse(response => response.url().endsWith('/internal/admin/platform/payment-destinations')
        && response.request().method() === 'POST');
    await destinations.getByRole('button', { name: 'Add destination' }).click();
    expect((await destinationCreated).status()).toBe(200);
    await expect(destinations.locator('input').nth(2)).toHaveValue('MT75 Fresh Bank');

    await page.goto('/internal/admin/pos/workspace/sales');
    await page.getByRole('button', { name: /MT75 Fresh Accessory/ }).click();
    const sale = page.getByRole('heading', { name: 'Sale & payment' }).locator('xpath=ancestor::section[1]');
    await sale.getByPlaceholder('Customer name (optional)').fill('MT75 Fresh Customer');
    await sale.getByPlaceholder('03XXXXXXXXX').fill('03001112222');
    await sale.getByPlaceholder('Customer email (optional)').fill('mt75-fresh-customer@example.invalid');
    await sale.getByRole('button', { name: 'Add Payment' }).click();
    await sale.getByPlaceholder('Amount').fill('150.00');
    await sale.getByPlaceholder('Safe reference').fill('MT75-FRESH-BANK-001');
    await page.getByTestId('server-totals').click();
    await expect(page.getByTestId('authoritative-totals')).toContainText('Remaining');
    await expect(page.getByTestId('authoritative-totals')).toContainText('PKR 0.00');
    const saleCreated = page.waitForResponse(response => response.url().endsWith('/internal/admin/pos/sales')
        && response.request().method() === 'POST');
    await page.getByTestId('finalize-sale').click();
    const salePayload = await (await saleCreated).json() as { data: { invoice_id: string } };
    await expect(page.getByTestId('sale-result')).toContainText('Sale complete:');
    await expect(page.getByTestId('sale-result')).toContainText('Final PKR 150.00');

    const returns = page.getByRole('heading', { name: 'Return / refund' }).locator('xpath=ancestor::section[1]');
    await returns.getByPlaceholder('Invoice public ID').fill(salePayload.data.invoice_id);
    await returns.getByRole('button', { name: 'Load invoice' }).click();
    await returns.locator('select').first().selectOption({ index: 1 });
    await returns.getByRole('button', { name: 'Accept return' }).click();
    await expect(returns).toContainText('Refund due: PKR 150.00');
    await returns.getByRole('button', { name: 'Record refund' }).click();
    await expect(page.getByTestId('refund-result')).toContainText('PKR 150.00 via bank_transfer');

    const reportResponse = await page.request.get('/internal/admin/pos/customer-reporting/reports');
    expect(reportResponse.status()).toBe(200);
    const reportPayload = await reportResponse.json() as { data: { report: {
        sales: { invoice_count: number; net_sales: string; return_net: string; refunds: string };
        payments: { pos_tender_total: string; method_breakdown: Record<string, string> };
    } } };
    expect(reportPayload.data.report.sales).toMatchObject({ invoice_count: 1, net_sales: '150.00', return_net: '150.00', refunds: '150.00' });
    expect(reportPayload.data.report.payments.pos_tender_total).toBe('150.00');
    expect(reportPayload.data.report.payments.method_breakdown.bank_transfer).toBe('150.00');
    await page.goto('/internal/admin/pos/workspace/reports');
    const reports = page.getByRole('heading', { name: 'Outlet dashboard & reports' }).locator('xpath=ancestor::section[1]');
    await expect(reports).toContainText('Payment Mix');
    await expect(reports).toContainText('POS:');
    await reports.getByRole('button', { name: 'Destination drill-down' }).click();
    await expect(reports).toContainText('MT75 Fresh Bank · bank_transfer · PKR 150.00');

    await page.goto('/internal/admin/pos/workspace/sales');
    await page.getByRole('button', { name: /MT75 Fresh Accessory/ }).click();
    const warrantySale = page.getByRole('heading', { name: 'Sale & payment' }).locator('xpath=ancestor::section[1]');
    await warrantySale.getByPlaceholder('Customer name (optional)').fill('MT75 Fresh Customer');
    await warrantySale.getByPlaceholder('03XXXXXXXXX').fill('03001112222');
    await warrantySale.getByRole('button', { name: 'Add Payment' }).click();
    await warrantySale.getByPlaceholder('Amount').fill('150.00');
    await warrantySale.getByPlaceholder('Safe reference').fill('MT75-FRESH-WARRANTY-002');
    await page.getByTestId('server-totals').click();
    await expect(page.getByTestId('authoritative-totals')).toContainText('PKR 0.00');
    const secondSaleCreated = page.waitForResponse(response => response.url().endsWith('/internal/admin/pos/sales') && response.request().method() === 'POST');
    await page.getByTestId('finalize-sale').click();
    const secondSalePayload = await (await secondSaleCreated).json() as { data: { invoice_id: string; sale_ids: string[] } };
    expect(secondSalePayload.data.sale_ids).toHaveLength(1);
    await expect(page.getByTestId('sale-result')).toContainText('Final PKR 150.00');

    await page.goto('/internal/admin/pos/workspace/claims');
    await expect(page.getByTestId('intake-sale')).toBeVisible();
    await page.getByTestId('intake-sale').selectOption(secondSalePayload.data.sale_ids[0]);
    await page.getByPlaceholder('Issue description').fill('Synthetic warranty defect requiring diagnosis');
    const claimCreated = page.waitForResponse(response => response.url().endsWith('/internal/admin/pos/customer-reporting/claims') && response.request().method() === 'POST');
    await page.getByTestId('claim-open').click();
    const claimPayload = await (await claimCreated).json() as { data: { claim_id: string; claim_number: string; status: string } };
    expect(claimPayload.data.status).toBe('received');
    const claimHistory = page.getByRole('heading', { name: 'Historical claims' }).locator('xpath=ancestor::section[1]');
    await expect(claimHistory).toContainText(claimPayload.data.claim_number);
    await claimHistory.locator('select').last().selectOption('diagnosing');
    await claimHistory.getByRole('button', { name: 'Update status' }).click();
    await expect(claimHistory).toContainText('diagnosing');
    const warrantyDocument = page.getByRole('heading', { name: 'Document Actions' }).locator('xpath=ancestor::section[1]');
    const previewRequest = page.waitForResponse(response => response.url().includes('/internal/admin/pos/customer-reporting/documents/warranty/') && response.url().includes('/preview?format=a4'));
    await warrantyDocument.getByRole('button', { name: 'Preview' }).click();
    const previewResponse = await previewRequest;
    expect(previewResponse.status()).toBe(200);
    const previewPayload = await previewResponse.json() as { data: { document_type: string; html: string } };
    expect(previewPayload.data.document_type).toBe('warranty');
    expect(previewPayload.data.html).toContain(claimPayload.data.claim_number);
    await expect(warrantyDocument).toContainText(claimPayload.data.claim_number);
    await page.goto('/internal/admin/pos/workspace/warranty');
    await expect(page.getByRole('heading', { name: 'Warranty Claim Receipts' }).locator('xpath=ancestor::section[1]')).toContainText('diagnosing');
    const joinedReport = await page.request.get('/internal/admin/pos/customer-reporting/reports');
    expect(joinedReport.status()).toBe(200);
    const joinedData = await joinedReport.json() as { data: { report: { sales: { invoice_count: number; net_sales: string; return_net: string; refunds: string }; payments: { pos_tender_total: string } } } };
    expect(joinedData.data.report.sales).toMatchObject({ invoice_count: 2, net_sales: '300.00', return_net: '150.00', refunds: '150.00' });
    expect(joinedData.data.report.payments.pos_tender_total).toBe('300.00');
    await page.getByTestId('logout').click();
    await page.waitForURL('**/internal/admin/pos/login');
});
