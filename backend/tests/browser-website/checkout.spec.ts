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

test.describe.configure({ mode: 'serial' });

async function login(page: import('@playwright/test').Page) {
    await page.goto('/account');
    await page.getByLabel('Email').fill('mt52-customer@example.invalid');
    await page.getByLabel('Password').fill('SyntheticPass123!');
    const loginReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/auth/login')
        && response.request().method() === 'POST');
    const accountReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/account') && response.status() === 200);
    await page.locator('form').getByRole('button', { name: 'Sign in', exact: true }).click();
    const loginResponse = await loginReady;
    expect(loginResponse.status(), 'customer login status').toBe(200);
    await accountReady;
    await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
}

test('MT-5.3 COD checkout exposes exactly four channels and preserves owned status/cancel flow', async ({ page }) => {
    test.setTimeout(120_000);

    await page.goto('/products/mt51-alpha-phone');
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to cart.', { exact: true })).toBeVisible();
    await login(page);

    const channelsReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/checkout/channels') && response.status() === 200);
    await page.goto('/checkout');
    await channelsReady;

    await expect(page.getByRole('radio')).toHaveCount(4);
    await expect(page.getByText('Cash on Delivery', { exact: true })).toBeVisible();
    await expect(page.getByText('JazzCash', { exact: true })).toBeVisible();
    await expect(page.getByText('Easypaisa', { exact: true })).toBeVisible();
    await expect(page.getByText('Credit / Debit Card', { exact: true })).toBeVisible();
    await expect(page.getByRole('radio', { name: /JazzCash/ })).toBeDisabled();
    await expect(page.getByRole('radio', { name: /Easypaisa/ })).toBeDisabled();
    await expect(page.getByRole('radio', { name: /Credit \/ Debit Card/ })).toBeDisabled();
    await expect(page.getByText('Bank transfer and split tender are not offered here.')).toBeVisible();

    await page.getByLabel('City').fill('Karachi');
    await page.getByLabel('Delivery address').fill('Synthetic MT-5.3 checkout address');
    const created = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/orders')
        && response.request().method() === 'POST'
        && response.status() === 201);
    await page.getByRole('button', { name: 'Place order' }).click();
    const createdResponse = await created;
    const body = await createdResponse.json() as { data: { order_id: string; payment_status: string } };
    expect(body.data.payment_status).toBe('pending_collection');

    await expect(page).toHaveURL('http://127.0.0.1:13000/account/orders/' + body.data.order_id);
    await expect(page.getByRole('heading', { name: 'Order status' })).toBeVisible();
    await expect(page.getByText('cod · pending_collection · PKR 50000.00')).toBeVisible();

    const cancelled = page.waitForResponse((response) =>
        response.url().endsWith('/cancel')
        && response.request().method() === 'POST'
        && response.status() === 200);
    await page.getByRole('button', { name: 'Cancel order' }).click();
    await cancelled;
    await expect(page.getByText(/cancelled · cancelled · unpaid/)).toBeVisible();

    await page.goto('/cart');
    await expect(page.getByText('Your cart is empty.', { exact: true })).toBeVisible();
});

test('MT-7.5 W04 synthetic hosted initiation failure keeps the created order recoverable without resubmission', async ({ page }) => {
    test.setTimeout(90_000);
    await page.goto('/products/mt51-alpha-phone');
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to cart.', { exact: true })).toBeVisible();
    await login(page);

    // Browser-only response stubs: no real wallet credentials, payment activation, or server-side order mutation.
    await page.route('**/api/customer/checkout/channels', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: [
            { code: 'cod', label: 'Cash on Delivery', available: true, kind: 'offline' },
            { code: 'jazzcash', label: 'JazzCash', available: true, kind: 'hosted' },
            { code: 'easypaisa', label: 'Easypaisa', available: false, kind: 'hosted' },
            { code: 'card', label: 'Credit / Debit Card', available: false, kind: 'hosted' },
        ] } }) });
    });
    const fakeOrderId = '00000000-0000-4000-8000-000000000075';
    const fakePaymentId = '00000000-0000-4000-8000-000000000076';
    let orderSubmits = 0;
    await page.route('**/api/customer/orders', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        orderSubmits += 1;
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: {
            order_id: fakeOrderId, order_number: 'MT75-SYNTHETIC-RECOVERY', payment_id: fakePaymentId,
            payment_status: 'pending', amount: '50000.00', currency: 'PKR',
        } }) });
    });
    let initiationAttempts = 0;
    await page.route(`**/api/customer/payments/${fakePaymentId}/initiate`, async (route) => {
        initiationAttempts += 1;
        await route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ message: 'Synthetic gateway temporarily unavailable.' }) });
    });

    await page.goto('/checkout');
    await expect(page.getByRole('radio', { name: /JazzCash/ })).toBeEnabled();
    await page.getByRole('radio', { name: /JazzCash/ }).check();
    await page.getByLabel('City').fill('Karachi');
    await page.getByLabel('Delivery address').fill('Synthetic W04 recovery address');
    await page.getByRole('button', { name: 'Place order' }).click();
    await expect(page.getByText('Synthetic gateway temporarily unavailable.', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'View order and continue payment' }))
        .toHaveAttribute('href', `/account/orders/${fakeOrderId}`);
    await expect(page.getByRole('button', { name: 'Place order' })).toHaveCount(0);
    expect(orderSubmits).toBe(1);
    expect(initiationAttempts).toBe(1);
});

test('MT-7.5 W04 insecure hosted redirect is blocked and the created order remains recoverable', async ({ page }) => {
    test.setTimeout(90_000);
    await page.goto('/products/mt51-alpha-phone');
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to cart.', { exact: true })).toBeVisible();
    await login(page);

    // Browser-only gateway responses. The external provider remains OFF and no real provider is contacted.
    await page.route('**/api/customer/checkout/channels', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: [
            { code: 'cod', label: 'Cash on Delivery', available: true, kind: 'offline' },
            { code: 'jazzcash', label: 'JazzCash', available: true, kind: 'hosted' },
            { code: 'easypaisa', label: 'Easypaisa', available: false, kind: 'hosted' },
            { code: 'card', label: 'Credit / Debit Card', available: false, kind: 'hosted' },
        ] } }) });
    });
    const fakeOrderId = '00000000-0000-4000-8000-000000000077';
    const fakePaymentId = '00000000-0000-4000-8000-000000000078';
    let orderSubmits = 0;
    let initiationAttempts = 0;
    await page.route('**/api/customer/orders', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        orderSubmits += 1;
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: {
            order_id: fakeOrderId, order_number: 'MT75-SYNTHETIC-UNSAFE-REDIRECT', payment_id: fakePaymentId,
            payment_status: 'pending', amount: '50000.00', currency: 'PKR',
        } }) });
    });
    await page.route(`**/api/customer/payments/${fakePaymentId}/initiate`, async (route) => {
        initiationAttempts += 1;
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            reference: 'MT75-SYNTHETIC-REFERENCE', redirect_url: 'http://example.invalid/unsafe-checkout',
        } }) });
    });

    await page.goto('/checkout');
    await expect(page.getByRole('radio', { name: /JazzCash/ })).toBeEnabled();
    await page.getByRole('radio', { name: /JazzCash/ }).check();
    await page.getByLabel('City').fill('Karachi');
    await page.getByLabel('Delivery address').fill('Synthetic W04 unsafe redirect address');
    await page.getByRole('button', { name: 'Place order' }).click();
    await expect(page.getByText('Payment provider returned an unsafe redirect.', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'View order and continue payment' }))
        .toHaveAttribute('href', `/account/orders/${fakeOrderId}`);
    await expect(page).toHaveURL('http://127.0.0.1:13000/checkout');
    await expect(page.getByRole('button', { name: 'Place order' })).toHaveCount(0);
    expect(orderSubmits).toBe(1);
    expect(initiationAttempts).toBe(1);
});

test('MT-5.3 digital-only mode prunes checkout while historical account stays available', async ({ page }) => {
    test.setTimeout(60_000);
    await login(page);
    state('digital_only');

    try {
        const checkout = await page.goto('/checkout');
        expect(checkout?.status()).toBe(404);
        await page.goto('/account');
        await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
        await expect(page.getByText('MT52-E2E-ORDER', { exact: true })).toBeVisible();
    } finally {
        state('hybrid');
    }
});
