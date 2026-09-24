import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function state(mode: 'hybrid' | 'digital_only' | 'commerce_only') {
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder',
        '--env=testing', '--force',
    ], {
        cwd: process.cwd(),
        stdio: 'inherit',
        env: { ...process.env, WEBSITE_E2E_MODE: mode, WEBSITE_E2E_ACTION: '' },
    });
}

async function login(page: import('@playwright/test').Page) {
    await page.goto('/account');
    await page.getByLabel('Email').fill('mt52-customer@example.invalid');
    await page.getByLabel('Password').fill('SyntheticPass123!');
    const loginReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/auth/login')
        && response.request().method() === 'POST');
    const accountReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/account') && response.status() === 200);
    const projectsReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/projects') && response.status() === 200);
    await page.locator('form').getByRole('button', { name: 'Sign in', exact: true }).click();
    const loginResponse = await loginReady;
    expect(loginResponse.status(), 'customer login status').toBe(200);
    await Promise.all([accountReady, projectsReady]);
    await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
}
test('MT-5.5 customer project portal preserves private history and truthful provider availability', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);

    const projectLink = page.getByRole('link', { name: 'MT55 Client Project', exact: true });
    await expect(projectLink).toBeVisible();
    await expect(projectLink.locator('..')).toContainText('delivered');
    const projectReady = page.waitForResponse((response) =>
        /\/api\/customer\/projects\/[0-9a-f-]+$/.test(response.url()) && response.status() === 200);
    await projectLink.click();
    await projectReady;

    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    await expect(page.getByText('MT55 Client Project Service · delivered')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Proposals & milestones' }).locator('..')).toContainText('MT55 Approved Scope');
    await expect(page.getByText('PKR 10000.00', { exact: true })).toBeVisible();
    await expect(page.getByText('No external payment provider is configured.', { exact: true })).toBeVisible();

    const files = page.getByRole('heading', { name: 'Private files' }).locator('..');
    await expect(files).toContainText('mt55-client-reference.txt');
    await expect(files).toContainText('mt55-delivery.txt');
    const downloadPromise = page.waitForEvent('download');
    await files.getByRole('link', { name: 'Download securely' }).last().click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBe('mt55-delivery.txt');

    const upload = page.waitForResponse((response) =>
        response.url().includes('/api/customer/projects/')
        && response.url().endsWith('/files/reference')
        && response.request().method() === 'POST'
        && response.status() === 201);
    await files.locator('input[type=file]').setInputFiles({
        name: 'mt55-browser-reference.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('MT55 browser private reference'),
    });
    await files.getByRole('button', { name: 'Upload reference' }).click();
    await upload;
    await expect(page.getByRole('status')).toHaveText('Reference file uploaded.');
    await expect(files).toContainText('mt55-browser-reference.txt');

    const history = page.getByRole('heading', { name: 'Project history' }).locator('..');
    await expect(history).toContainText('proposal approved');
    await expect(history.getByText('project status changed', { exact: true })).toHaveCount(4);

    state('commerce_only');
    await page.reload();
    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    state('digital_only');
    await page.reload();
    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    state('hybrid');
});

test('W04 project milestone initiation failure retains its owned order for continuation without another order', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);
    const fakeOrderId = '00000000-0000-4000-8000-000000000085';
    const fakePaymentId = '00000000-0000-4000-8000-000000000086';
    await page.route('**/api/customer/project-payment-channels', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: [
            { code: 'jazzcash', label: 'JazzCash', instructions: 'Use hosted wallet checkout.', available: true },
            { code: 'easypaisa', label: 'Easypaisa', available: false },
            { code: 'card', label: 'Credit / Debit Card', available: false },
        ] } }) });
    });
    let creates = 0;
    await page.route('**/api/customer/project-milestones/pay', async (route) => {
        creates += 1;
        await new Promise((resolve) => setTimeout(resolve, 350));
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: {
            order_id: fakeOrderId, payment_id: fakePaymentId,
        } }) });
    });
    let initiations = 0;
    await page.route(`**/api/customer/payments/${fakePaymentId}/initiate`, async (route) => {
        initiations += 1;
        await route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ message: 'Synthetic milestone gateway unavailable.' }) });
    });
    await page.route(`**/api/customer/orders/${fakeOrderId}`, async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            id: fakeOrderId, number: 'MT75-PROJECT-RECOVERY', type: 'digital',
            status: 'pending', fulfillment_status: 'pending', payment_status: 'unpaid',
            subtotal: '10000.00', total: '10000.00', currency: 'PKR', items: [],
            payments: [{ public_id: fakePaymentId, gateway: 'jazzcash', status: 'pending', amount: '10000.00', currency: 'PKR' }],
            signed_access_url: 'https://example.invalid/synthetic-status',
        } }) });
    });
    await page.getByRole('link', { name: 'MT55 Client Project', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Pay with JazzCash' })).toBeEnabled();
    await expect(page.getByText('Use hosted wallet checkout.', { exact: true })).toBeVisible();
    await page.evaluate(() => {
        const pay = [...document.querySelectorAll('button')].find((button) => button.textContent === 'Pay with JazzCash');
        if (!pay) throw new Error('Synthetic milestone payment option missing.');
        pay.click(); pay.click();
    });
    await expect(page.getByRole('status')).toHaveText('Synthetic milestone gateway unavailable.');
    await expect(page.getByRole('link', { name: 'View order and continue payment' }))
        .toHaveAttribute('href', `/account/orders/${fakeOrderId}`);
    await expect(page.getByRole('button', { name: 'Pay with JazzCash' })).toBeDisabled();
    expect(creates).toBe(1);
    expect(initiations).toBe(1);
    await page.getByRole('link', { name: 'View order and continue payment' }).click();
    await expect(page).toHaveURL(`http://127.0.0.1:13000/account/orders/${fakeOrderId}`);
    await expect(page.getByRole('heading', { name: 'MT75-PROJECT-RECOVERY' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cancel order' })).toHaveCount(0);
    expect(creates).toBe(1);
});


test('W04 failed digital milestone order does not offer commerce-only retry', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);
    const orderId = '00000000-0000-4000-8000-000000000087';
    await page.route('**/api/customer/checkout/channels', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: [
            { code: 'cod', label: 'Cash on Delivery', available: true },
            { code: 'jazzcash', label: 'JazzCash', available: true },
            { code: 'easypaisa', label: 'Easypaisa', available: false },
            { code: 'card', label: 'Credit / Debit Card', available: false },
        ] } }) });
    });
    await page.route('**/api/customer/orders/' + orderId, async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            id: orderId, number: 'MT75-FAILED-PROJECT', type: 'digital', status: 'pending',
            fulfillment_status: 'pending', payment_status: 'failed', subtotal: '10000.00', total: '10000.00',
            currency: 'PKR', items: [], signed_access_url: '/account', payments: [
                { public_id: '00000000-0000-4000-8000-000000000088', gateway: 'jazzcash', status: 'failed', amount: '10000.00', currency: 'PKR' },
            ],
        } }) });
    });
    await page.goto('/account/orders/' + orderId);
    await expect(page.getByRole('heading', { name: 'MT75-FAILED-PROJECT' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retry with JazzCash' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Continue payment' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Cancel order' })).toHaveCount(0);
});

test('W04 owned pending digital order reports missing hosted continuation without creating another payment', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);
    const orderId = '00000000-0000-4000-8000-000000000091';
    const paymentId = '00000000-0000-4000-8000-000000000092';
    let initiations = 0;
    await page.route('**/api/customer/orders/' + orderId, async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            id: orderId, number: 'MT75-PROJECT-NO-REDIRECT', type: 'digital', status: 'pending',
            fulfillment_status: 'pending', payment_status: 'unpaid', subtotal: '10000.00', total: '10000.00',
            currency: 'PKR', items: [], signed_access_url: '/account', payments: [
                { public_id: paymentId, gateway: 'jazzcash', status: 'pending', amount: '10000.00', currency: 'PKR' },
            ],
        } }) });
    });
    await page.route(`**/api/customer/payments/${paymentId}/initiate`, async (route) => {
        initiations += 1;
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { payment_id: paymentId } }) });
    });
    await page.goto('/account/orders/' + orderId);
    await expect(page.getByRole('heading', { name: 'MT75-PROJECT-NO-REDIRECT' })).toBeVisible();
    await page.getByRole('button', { name: 'Continue payment' }).click();
    await expect(page.getByText('Payment provider did not return a continuation URL. Your order is saved; try continuing from this page.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Retry with JazzCash' })).toHaveCount(0);
    expect(initiations).toBe(1);
});