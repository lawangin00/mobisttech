import { expect, Page, test } from '@playwright/test';

const password = 'SyntheticPass123!';
const invoiceId = '11111111-aaaa-4111-8111-111111111111';
const claimId = '22222222-bbbb-4222-8222-222222222222';
const saleId = '33333333-cccc-4333-8333-333333333333';
const productId = '44444444-dddd-4444-8444-444444444444';
const destinationId = '55555555-eeee-4555-8555-555555555555';

async function login(page: Page) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-mt43@example.invalid');
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

test('MT-4.3 customer warranty reporting document flow is explicit role scoped and responsive', async ({ page }) => {
    let emailPosts = 0;
    let claimStatus = 'received';
    let lastPreviewFormat = '';
    const deliveryHistory: Array<Record<string, unknown>> = [];
    let popupCount = 0;
    let downloadCount = 0;
    page.on('popup', async (popup) => { popupCount += 1; await popup.close().catch(() => undefined); });
    page.on('download', () => { downloadCount += 1; });

    const invoice = {
        id: invoiceId, number: 'INV-E41-MT43', customer_id: 1, customer_name: 'E2E MT43 Customer',
        customer_phone: '03001234567', customer_email: 'mt43@example.invalid', customer_cnic: '42101-1234567-1',
        salesperson_name: 'E2E MT43 Manager', final_bill: '200.02', currency: 'PKR', created_at: '2026-09-18 07:00:00',
    };
    const claimRow = {
        id: claimId, number: 'MST-CLM-E41-MT43', status: claimStatus, version: claimStatus === 'received' ? 1 : 2,
        quantity: 1, received_at: '2026-09-18 07:30:00', expected_completion_at: null,
        invoice_id: invoiceId, invoice_number: invoice.number, customer_name: invoice.customer_name,
        customer_phone: invoice.customer_phone, product_name: 'E2E MT43 Phone',
    };
    const claimDetail = () => ({
        claim_id: claimId, claim_number: claimRow.number, version: claimStatus === 'received' ? 1 : 2,
        invoice_id: invoiceId, invoice_number: invoice.number, customer_name: invoice.customer_name,
        customer_phone: invoice.customer_phone, customer_cnic: invoice.customer_cnic, business: {},
        product_id: productId, product_code: 'MT43-PHONE', product_name: 'E2E MT43 Phone',
        stock_unit_id: null, unit_code: null, unit_color: null, unit_condition: null, quantity: 1,
        status: claimStatus, issue_description: 'Synthetic E2E warranty issue', received_condition: 'Used',
        accessories_received: 'Handset only', assigned_to: 'Bench MT43', diagnosis: claimStatus === 'diagnosing' ? 'E2E diagnosis' : null,
        resolution: null, internal_notes: null, received_at: claimRow.received_at, expected_completion_at: null,
        resolved_at: null, delivered_at: null, customer_satisfied: null, follow_up_required: false,
        follow_up_at: null, follow_up_notes: null, handled_by_name: 'E2E MT43 Manager',
        warranty: { type: 'shop_warranty', duration: 30, unit: 0, expires_at: '2026-10-18 07:00:00.000000' },
        activity_log: claimStatus === 'received'
            ? [{ at: claimRow.received_at, status: 'received', note: 'Warranty job received from customer.', actor: 'E2E MT43 Manager' }]
            : [
                { at: claimRow.received_at, status: 'received', note: 'Warranty job received from customer.', actor: 'E2E MT43 Manager' },
                { at: '2026-09-18 07:40:00', status: 'diagnosing', note: 'Status changed from received to diagnosing.', actor: 'E2E MT43 Manager' },
            ],
        imeis: [],
    });
    const report = {
        outlet_id: 'e2e-outlet', from: null, to: null,
        sales: { invoice_count: 1, gross_sales: '200.02', discounts: '0.00', net_sales: '200.02', gross_profit: '50.00', return_net: '0.00', refunds: '0.00', note: 'Invoice totals are counted once; tender allocations and settlements are separate measures.' },
        payments: {
            pos_tender_total: '200.02', website_payment_total: '75.00', website_gateway_breakdown: { easypaisa: '75.00' },
            method_breakdown: { card: '50.00', bank_transfer: '150.02' },
            destination_breakdown: [
                { method: 'card', destination: 'E2E Terminal', amount: '50.00' },
                { method: 'bank_transfer', destination: 'E2E Bank', amount: '150.02' },
            ],
            provider_fees: '3.00', expected_settlement: '197.02', net_settlement: '197.02',
            settlement_variance: '0.00', unreconciled_tenders: 0,
        },
        activity: { procurement_orders: 1, stocktakes: 1, transfers_out: 0, cash_entries: 2, trade_ins: 1, repairs: 1, loyalty_entries: 1, promotion_claims: 0 },
    };
    const areaData = (area: string) => ({
        area, outlet: { id: 'e2e-outlet', name: 'E2E Sales Outlet' }, can_send_documents: true,
        invoices: area === 'invoices' ? [invoice] : [],
        customers: area === 'invoices' ? [{ id: 'customer-mt43', name: invoice.customer_name, email: invoice.customer_email, mobile: invoice.customer_phone, version: 1, invoices: [invoice] }] : [],
        claims: ['warranty', 'claims'].includes(area) ? [{ ...claimRow, status: claimStatus, version: claimStatus === 'received' ? 1 : 2 }] : [],
        sale_candidates: area === 'claims' ? [{
            sale_id: saleId, invoice_id: invoiceId, invoice_number: invoice.number, customer_name: invoice.customer_name,
            product_name: 'E2E MT43 Phone', quantity: 1, returned_quantity: 0, track_imei: false, units: [],
            warranty_type: 'shop_warranty', warranty_unit: 0, warranty_duration: 30,
        }] : [],
        report: area === 'reports' ? report : null,
    });
    const docRender = (type: string, format: string, action: string) => ({
        document_type: type, format, document_version: 1,
        filename: (type === 'invoice' ? invoice.number : claimRow.number) + '-' + format + '.pdf',
        document_sha256: 'a'.repeat(64), html: '<article data-contract="canonical-document.v1"><div>' + (type === 'invoice' ? invoice.number : claimRow.number) + '</div><div>' + format + '</div></article>',
        action,
    });

    await page.route('**/internal/admin/pos/catalogue*', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            outlet: { id: 'e2e-outlet', name: 'E2E Sales Outlet' },
            products: [{ id: productId, name: 'E2E MT43 Phone', code: 'MT43-PHONE', qty: 2, purchase_price: '150.00', sale_price: '200.02', track_imei: false, units: [] }],
            page: 1, has_more: false,
            payment_destinations: [{ public_id: destinationId, method: 'card', display_name: 'E2E Terminal', provider_label: 'E2E', masked_identifier: 'TERM-43' }],
            master_data: [], can_send_documents: true,
        } }) });
    });
    await page.route('**/internal/admin/pos/sales/quote', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { payable: '200.02', payments_total: '200.02', remaining: '0.00', cash_change: '0.00' } }) });
    });
    await page.route('**/internal/admin/pos/sales', async (route) => {
        if (route.request().method() === 'POST') {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
                invoice_id: invoiceId, invoice_number: invoice.number, final_bill: '200.02', discount: '0.00',
                cash_change_total: '0.00', paid_amount: '200.02', remaining: '0.00', sale_ids: [saleId], payments: [],
            } }) });
            return;
        }
        await route.fallback();
    });

    await page.route('**/internal/admin/pos/customer-reporting/**', async (route) => {
        const request = route.request();
        const url = new URL(request.url());
        const path = url.pathname;
        const method = request.method();

        const areaMatch = path.match(/\/customer-reporting\/(invoices|warranty|claims|reports)$/);
        if (method === 'GET' && areaMatch) {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: areaData(areaMatch[1]) }) });
            return;
        }
        if (method === 'GET' && path.endsWith('/report/csv')) {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { filename: 'pos-report.csv', csv: 'section,metric,value\nsales,invoice_count,1\npayments,pos_tender_total,200.02\n' } }) });
            return;
        }
        if (method === 'GET' && path.endsWith('/report/summary')) {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: report }) });
            return;
        }
        if (path.endsWith('/claims/' + claimId) && method === 'GET') {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: claimDetail() }) });
            return;
        }
        if (path.endsWith('/claims/' + claimId) && method === 'POST') {
            claimStatus = 'diagnosing';
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: claimDetail() }) });
            return;
        }
        if (path.endsWith('/claims') && method === 'POST') {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: claimDetail() }) });
            return;
        }
        const documentMatch = path.match(/\/documents\/(invoice|warranty)\/([^/]+)\/(preview|print|pdf|email-draft|email|whatsapp|history)$/);
        if (documentMatch) {
            const [, type, , action] = documentMatch;
            const format = url.searchParams.get('format') ?? 'a4';
            if (method === 'GET' && action === 'preview') {
                lastPreviewFormat = format;
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: docRender(type, format, 'preview') }) });
                return;
            }
            if (method === 'GET' && action === 'print') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: docRender(type, format, 'print') }) });
                return;
            }
            if (method === 'GET' && action === 'pdf') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { ...docRender(type, format, 'save_pdf'), pdf_base64: btoa('%PDF-1.4 MT43 synthetic') } }) });
                return;
            }
            if (method === 'GET' && action === 'email-draft') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
                    ...docRender(type, 'a4', 'preview'), to: invoice.customer_email, subject: 'Your mobiST document', message: 'Attached document',
                } }) });
                return;
            }
            if (method === 'POST' && action === 'email') {
                emailPosts += 1;
                if (emailPosts === 1) {
                    deliveryHistory.push({ attempt_id: 'failed-email', document_type: type, channel: 'email', state: 'failed', recipient: invoice.customer_email, failure_summary: 'Gmail is not connected.', intentional_resend: false, prepared_at: '2026-09-18', completed_at: '2026-09-18' });
                    await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Gmail is not connected.' }) });
                    return;
                }
                deliveryHistory.push({ attempt_id: 'sent-email', document_type: type, channel: 'email', state: 'sent', recipient: invoice.customer_email, failure_summary: null, intentional_resend: true, prepared_at: '2026-09-18', completed_at: '2026-09-18' });
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: deliveryHistory.at(-1) }) });
                return;
            }
            if (method === 'POST' && action === 'whatsapp') {
                const prepared = {
                    attempt_id: 'wa-mt43', document_type: type, channel: 'whatsapp', state: 'prepared', recipient: '923001234567',
                    document_sha256: 'a'.repeat(64), provider_reference: null, failure_summary: null, intentional_resend: false,
                    prepared_at: '2026-09-18', completed_at: null, phone: '923001234567', message: 'Your mobiST document',
                    whatsapp_uri: 'https://wa.me/923001234567?text=Your%20mobiST%20document',
                    operator_instruction: 'Attach the generated PDF before sending.',
                    attachment: { filename: 'document-a4.pdf', sha256: 'a'.repeat(64), pdf: '%PDF-1.4' },
                };
                deliveryHistory.push(prepared);
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: prepared }) });
                return;
            }
            if (method === 'GET' && action === 'history') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: deliveryHistory }) });
                return;
            }
        }
        if (method === 'POST' && path.includes('/documents/whatsapp/') && path.endsWith('/opened')) {
            const row = deliveryHistory.find((item) => item.attempt_id === 'wa-mt43');
            if (row) row.state = 'opened';
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: row }) });
            return;
        }
        await route.fallback();
    });

    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page);
    await expect(page.getByRole('link', { name: 'Sales', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Invoices & customers', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Warranty', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Claims', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Reports', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Inventory', exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Operations', exact: true })).toHaveCount(0);

    await page.getByRole('link', { name: 'Sales', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/sales');
    await page.getByRole('button', { name: /E2E MT43 Phone/ }).click();
    await page.getByPlaceholder('Customer name (optional)').fill('E2E MT43 Customer');
    await page.getByPlaceholder('Customer email (optional)').fill('mt43@example.invalid');
    await page.getByRole('button', { name: 'Add Payment' }).click();
    await page.getByPlaceholder('Amount').fill('200.02');
    await page.getByPlaceholder('Safe reference').fill('MT43-E2E');
    await page.locator('select').filter({ has: page.locator('option[value="thermal80"]') }).selectOption('thermal80');
    await page.getByTestId('server-totals').click();
    await expect(page.getByTestId('authoritative-totals')).toContainText('PKR 200.02');
    expect(popupCount).toBe(0);
    expect(downloadCount).toBe(0);
    await page.getByTestId('finalize-sale').click();
    await expect(page.getByTestId('sale-result')).toContainText(invoice.number);
    expect(popupCount).toBe(0);
    expect(downloadCount).toBe(0);
    const saleDocs = page.getByRole('heading', { name: 'Document Actions' }).locator('xpath=ancestor::section[1]');
    await saleDocs.getByRole('button', { name: 'Preview', exact: true }).click();
    await expect(saleDocs.locator('[data-contract="canonical-document.v1"]')).toContainText(invoice.number);
    expect(lastPreviewFormat).toBe('thermal80');

    const popupPromise = page.waitForEvent('popup');
    await saleDocs.getByRole('button', { name: 'Print', exact: true }).click();
    await (await popupPromise).close().catch(() => undefined);
    const downloadPromise = page.waitForEvent('download');
    await saleDocs.getByRole('button', { name: 'Save PDF', exact: true }).click();
    await downloadPromise;
    expect(popupCount).toBeGreaterThan(0);
    expect(downloadCount).toBeGreaterThan(0);

    await saleDocs.getByRole('button', { name: 'Send via Email' }).click();
    await saleDocs.getByRole('button', { name: 'Confirm Email Send' }).click();
    await expect(page.getByRole('alert')).toContainText('Gmail is not connected.');
    await saleDocs.getByRole('button', { name: 'Confirm Email Send' }).click();
    await expect(saleDocs).toContainText('email · sent');
    await saleDocs.getByRole('button', { name: 'Send via WhatsApp' }).click();
    await expect(saleDocs).toContainText('Attach the generated PDF before sending.');
    await expect(saleDocs).toContainText('opening WhatsApp is not recorded as sent');

    await page.getByRole('link', { name: 'Invoices & customers', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/invoices');
    const invoiceHistory = page.getByRole('heading', { name: 'Customers & invoice history' }).locator('xpath=ancestor::section[1]');
    await expect(invoiceHistory).toBeVisible();
    await invoiceHistory.locator('select').first().selectOption('customer-mt43');
    await expect(invoiceHistory).toContainText('mt43@example.invalid');
    const invoiceDocs = page.getByRole('heading', { name: 'Document Actions' }).locator('xpath=ancestor::section[1]');
    await invoiceDocs.getByRole('button', { name: 'Delivery history' }).click();
    await expect(invoiceDocs).toContainText('email · sent');
    await expect(invoiceDocs).toContainText('whatsapp · prepared');

    await page.getByRole('link', { name: 'Warranty', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/warranty');
    await expect(page.getByRole('heading', { name: 'Warranty Claim Receipts' })).toBeVisible();
    const warrantyDocs = page.getByRole('heading', { name: 'Document Actions' }).locator('xpath=ancestor::section[1]');
    await expect(warrantyDocs.locator('select')).toHaveCount(0);
    await warrantyDocs.getByRole('button', { name: 'Preview', exact: true }).click();
    await expect(warrantyDocs.locator('[data-contract="canonical-document.v1"]')).toContainText(claimRow.number);
    expect(lastPreviewFormat).toBe('a4');

    await page.getByRole('link', { name: 'Claims', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/claims');
    await page.getByRole('heading', { name: 'Historical claims' }).locator('xpath=ancestor::section[1]').locator('select').selectOption(claimId);
    const claimSection = page.getByRole('heading', { name: 'Historical claims' }).locator('xpath=ancestor::section[1]');
    await expect(claimSection).toContainText('received');
    await claimSection.locator('select').nth(1).selectOption('diagnosing');
    await claimSection.getByPlaceholder('Diagnosis').fill('E2E diagnosis');
    await claimSection.getByRole('button', { name: 'Update status' }).click();
    await expect(claimSection).toContainText('diagnosing');

    await page.getByRole('link', { name: 'Reports', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/reports');
    const reports = page.getByRole('heading', { name: 'Outlet dashboard & reports' }).locator('xpath=ancestor::section[1]');
    await expect(reports).toContainText('Payment Mix');
    await expect(reports).toContainText('POS:');
    await expect(reports).toContainText('Website:');
    await expect(reports).not.toContainText('E2E Terminal · card');
    await reports.getByRole('button', { name: 'Destination drill-down' }).click();
    await expect(reports).toContainText('E2E Terminal · card · PKR 50.00');
    const csvDownload = page.waitForEvent('download');
    await reports.getByRole('button', { name: 'Export CSV' }).click();
    await csvDownload;

    await page.setViewportSize({ width: 390, height: 844 });
    for (const name of ['Invoices & customers', 'Warranty', 'Reports']) {
        const mobileLink = page.locator('header').getByRole('link', { name, exact: true });
        if (!(await mobileLink.isVisible())) {
            await page.getByText('Menu', { exact: true }).click();
        }
        await mobileLink.click();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(overflow).toBe(false);
    }

    await page.getByTestId('logout').click();
    await page.waitForURL('**/internal/admin/pos/login');
});
