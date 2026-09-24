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

// The isolated PHP/Next proxy can finish the session account response after the channel
// response. Wait for both actual HTTP results before asserting the hydrated radio inputs.
async function checkoutReady(page: import('@playwright/test').Page) {
    const account = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/account') && response.status() === 200, { timeout: 20_000 });
    const channels = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/checkout/channels') && response.status() === 200, { timeout: 20_000 });
    await page.goto('/checkout');
    await Promise.all([account, channels]);
    await expect(page.getByRole('radio')).toHaveCount(4, { timeout: 15_000 });
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

    await checkoutReady(page);
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
    // Real owned-order API and rendered UI must expose the order-bound terms, not a live policy lookup.
    const orderDetail = await page.request.get('/api/customer/orders/' + body.data.order_id);
    expect(orderDetail.status()).toBe(200);
    const orderJson = await orderDetail.json() as { data: { payment_terms: { gateway: string; label: string; instructions: string; presentation_version: number } | null } };
    expect(orderJson.data.payment_terms).toMatchObject({ gateway: 'cod', label: 'Cash on Delivery', presentation_version: 0 });
    await expect(page.getByRole('heading', { name: 'Payment terms at order placement' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Payment terms at order placement' }).locator('..')).toContainText('Cash on Delivery');
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
            { code: 'cod', label: 'Cash on Delivery', available: true, kind: 'offline', cod_min_amount: '100.00', cod_max_amount: '500.00' },
            { code: 'jazzcash', label: 'JazzCash', available: true, kind: 'hosted' },
            { code: 'easypaisa', label: 'Easypaisa', available: false, kind: 'hosted' },
            { code: 'card', label: 'Credit / Debit Card', available: false, kind: 'hosted' },
        ] } }) });
    });
    const fakeOrderId = '00000000-0000-4000-8000-000000000075';
    const fakePaymentId = '00000000-0000-4000-8000-000000000076';
    // Complete the already-visible recovery link against a browser-only owned-order fixture.
    // The real backend retains default-OFF external channels and receives no synthetic payment.
    await page.route(`**/api/customer/orders/${fakeOrderId}`, async (route) => {
        if (route.request().method() !== 'GET') return route.continue();
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            id: fakeOrderId, number: 'MT75-SYNTHETIC-RECOVERY', type: 'commerce',
            status: 'pending', fulfillment_status: 'pending', payment_status: 'unpaid',
            subtotal: '50000.00', total: '50000.00', currency: 'PKR', items: [],
            payments: [{ public_id: fakePaymentId, gateway: 'jazzcash', status: 'pending',
                amount: '50000.00', currency: 'PKR' }],
            signed_access_url: 'https://example.invalid/readonly-synthetic-order',
        } }) });
    });
    let orderSubmits = 0;
    await page.route('**/api/customer/orders', async (route) => {
        if (route.request().method() !== 'POST') return route.continue();
        orderSubmits += 1;
        // Hold the first order creation open so both submit events arrive before React re-renders.
        await new Promise((resolve) => setTimeout(resolve, 350));
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

    await checkoutReady(page);
    await expect(page.getByText('COD order amount: 100.00 – 500.00 PKR. Final eligibility is checked when placing the order.')).toBeVisible();
    await expect(page.getByRole('radio', { name: /JazzCash/ })).toBeEnabled();
    await page.getByRole('radio', { name: /JazzCash/ }).check();
    await page.getByLabel('City').fill('Karachi');
    await page.getByLabel('Delivery address').fill('Synthetic W04 recovery address');
    // Two same-tick submit events must produce one order POST and one payment initiation.
    await page.evaluate(() => {
        const button = [...document.querySelectorAll('button')].find((item) => item.textContent === 'Place order');
        if (!button) throw new Error('Place order button missing from checkout.');
        button.click();
        button.click();
    });
    await expect(page.getByText('Synthetic gateway temporarily unavailable.', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'View order and continue payment' }))
        .toHaveAttribute('href', `/account/orders/${fakeOrderId}`);
    await expect(page.getByRole('button', { name: 'Place order' })).toHaveCount(0);
    expect(orderSubmits).toBe(1);
    expect(initiationAttempts).toBe(1);
    await page.getByRole('link', { name: 'View order and continue payment' }).click();
    await expect(page).toHaveURL(`http://127.0.0.1:13000/account/orders/${fakeOrderId}`);
    await expect(page.getByRole('heading', { name: 'MT75-SYNTHETIC-RECOVERY' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toBeVisible();
    expect(orderSubmits).toBe(1);
    // Double-click while pending must not create duplicate external-initiation requests.
    await page.route(`**/api/customer/payments/${fakePaymentId}/initiate`, async (route) => {
        initiationAttempts += 1;
        await new Promise((resolve) => setTimeout(resolve, 350));
        await route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ message: 'Synthetic gateway temporarily unavailable.' }) });
    });
    await page.evaluate(() => {
        const button = [...document.querySelectorAll('button')].find((item) => item.textContent === 'Continue payment');
        if (!button) throw new Error('Continue payment button missing from owned order.');
        button.click();
        button.click();
    });
    await expect(page.getByText('Synthetic gateway temporarily unavailable.', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toBeEnabled();
    expect(initiationAttempts).toBe(2);
    expect(orderSubmits).toBe(1);
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
            { code: 'cod', label: 'Cash on Delivery', available: true, kind: 'offline', cod_min_amount: '100.00', cod_max_amount: '500.00' },
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

    await checkoutReady(page);
    await expect(page.getByText('COD order amount: 100.00 – 500.00 PKR. Final eligibility is checked when placing the order.')).toBeVisible();
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

test('MT-7.5 W04 all unavailable checkout channels cannot submit a Website order', async ({ page }) => {
    test.setTimeout(90_000);
    await page.goto('/products/mt51-alpha-phone');
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to cart.', { exact: true })).toBeVisible();
    await login(page);
    let orderSubmits = 0;
    await page.route('**/api/customer/orders', async (route) => {
        if (route.request().method() === 'POST') orderSubmits += 1;
        await route.continue();
    });
    await page.route('**/api/customer/checkout/channels', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: [
            { code: 'cod', label: 'Cash on Delivery', available: false, kind: 'offline' },
            { code: 'jazzcash', label: 'JazzCash', available: false, kind: 'hosted' },
            { code: 'easypaisa', label: 'Easypaisa', available: false, kind: 'hosted' },
            { code: 'card', label: 'Credit / Debit Card', available: false, kind: 'hosted' },
        ] } }) });
    });
    await checkoutReady(page);
    await expect(page.getByRole('radio')).toHaveCount(4);
    for (const channel of ['Cash on Delivery', 'JazzCash', 'Easypaisa', 'Credit / Debit Card']) {
        await expect(page.getByRole('radio', { name: channel })).toBeDisabled();
    }
    await expect(page.getByRole('button', { name: 'Place order' })).toBeDisabled();
    expect(orderSubmits).toBe(0);
});

test('MT-7.5 W04 cancelled external order cannot display continue-payment action', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);
    const fakeOrderId = '00000000-0000-4000-8000-000000000079';
    const fakePaymentId = '00000000-0000-4000-8000-000000000080';
    let paymentInitiations = 0;
    await page.route(`**/api/customer/payments/${fakePaymentId}/initiate`, async (route) => {
        paymentInitiations += 1;
        await route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ message: 'Cancelled order.' }) });
    });
    await page.route(`**/api/customer/orders/${fakeOrderId}`, async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            id: fakeOrderId, number: 'MT75-CANCELLED-EXTERNAL', type: 'commerce', status: 'cancelled',
            fulfillment_status: 'cancelled', payment_status: 'unpaid', subtotal: '50000.00', total: '50000.00',
            currency: 'PKR', items: [], signed_access_url: '/account', payments: [
                { public_id: fakePaymentId, gateway: 'jazzcash', status: 'pending', amount: '50000.00', currency: 'PKR' },
            ],
        } }) });
    });
    await page.goto(`/account/orders/${fakeOrderId}`);
    await expect(page.getByRole('heading', { name: 'MT75-CANCELLED-EXTERNAL' })).toBeVisible();
    await expect(page.getByText('jazzcash', { exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Retry with JazzCash' })).toHaveCount(0);
    expect(paymentInitiations).toBe(0);
});

test('W04 definitively failed commerce order offers authorized retry after provider cancellation', async ({ page }) => {
    test.setTimeout(90_000);
    // Independent sixth synthetic login shares this fixture's IP throttle bucket.
    // Clear disposable testing cache only; production request limits stay enabled.
    execFileSync('php', ['artisan', 'cache:clear', '--env=testing'], { cwd: process.cwd(), stdio: 'inherit' });
    await login(page);
    const orderId = '00000000-0000-4000-8000-000000000093';
    const failedPaymentId = '00000000-0000-4000-8000-000000000094';
    const newPaymentId = '00000000-0000-4000-8000-000000000095';
    let retries = 0;
    let retryCreated = false;
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
            id: orderId, number: 'MT75-COMMERCE-FAILED-RETRY', type: 'commerce', status: retryCreated ? 'pending' : 'cancelled',
            fulfillment_status: retryCreated ? 'pending' : 'cancelled', payment_status: retryCreated ? 'unpaid' : 'failed', subtotal: '50000.00', total: '50000.00',
            currency: 'PKR', items: [], signed_access_url: '/account', payments: [
                { public_id: failedPaymentId, gateway: 'jazzcash', status: 'failed', amount: '50000.00', currency: 'PKR' },
                ...(retryCreated ? [{ public_id: newPaymentId, gateway: 'jazzcash', status: 'pending', amount: '50000.00', currency: 'PKR' }] : []),
            ],
        } }) });
    });
    await page.route('**/api/customer/orders/' + orderId + '/payments/retry', async (route) => {
        retries += 1;
        await new Promise((resolve) => setTimeout(resolve, 350));
        retryCreated = true;
        expect(route.request().postDataJSON()).toEqual({ gateway: 'jazzcash' });
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: { payment_id: newPaymentId } }) });
    });
    await page.route('**/api/customer/payments/' + newPaymentId + '/initiate', async (route) => {
        await route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ message: 'Synthetic provider unavailable.' }) });
    });
    await page.goto('/account/orders/' + orderId);
    await expect(page.getByRole('heading', { name: 'MT75-COMMERCE-FAILED-RETRY' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retry with JazzCash' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Cancel order' })).toHaveCount(0);
    await page.evaluate(() => {
        const retry = [...document.querySelectorAll('button')].find((button) => button.textContent === 'Retry with JazzCash');
        if (!retry) throw new Error('Synthetic retry button missing.');
        retry.click(); retry.click();
    });
    await expect(page.getByText('Synthetic provider unavailable.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retry with JazzCash' })).toHaveCount(0);
    expect(retries).toBe(1);
});
test('MT-5.3 digital-only mode prunes checkout while historical account stays available', async ({ page }) => {
    test.setTimeout(60_000);
    // Independent synthetic journey #6 reuses the same localhost test identity; reset
    // disposable testing limiter state between journeys, not production limits.
    execFileSync('php', ['artisan', 'cache:clear', '--env=testing'], { cwd: process.cwd(), stdio: 'inherit' });
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
