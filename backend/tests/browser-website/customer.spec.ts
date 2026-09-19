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

async function login(page: import('@playwright/test').Page, remember = false) {
    await page.goto('/account');
    await page.getByLabel('Email').fill('mt52-customer@example.invalid');
    await page.getByLabel('Password').fill('SyntheticPass123!');
    if (remember) await page.getByLabel('Remember me on this device').check();
    const loginReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/auth/login')
        && response.request().method() === 'POST');
    const accountReady = page.waitForResponse((response) => response.url().endsWith('/api/customer/account') && response.status() === 200);
    const ordersReady = page.waitForResponse((response) => response.url().endsWith('/api/customer/orders') && response.status() === 200);
    const wishlistReady = page.waitForResponse((response) => response.url().endsWith('/api/customer/wishlist') && response.status() === 200);
    const reviewsReady = page.waitForResponse((response) => response.url().endsWith('/api/customer/reviews') && response.status() === 200);
    await page.locator('form').getByRole('button', { name: 'Sign in', exact: true }).click();
    const loginResponse = await loginReady;
    expect(loginResponse.status(), 'customer login status').toBe(200);
    await accountReady;
    await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
    await Promise.all([ordersReady, wishlistReady, reviewsReady]);
}

test.describe.configure({ mode: 'serial' });

test('MT-5.2 guest cart persists and saved ownership is claimed on remembered login', async ({ page, context }) => {
    test.setTimeout(90_000);

    await page.goto('/products/mt51-alpha-phone');
    await page.getByRole('button', { name: 'Save for later' }).click();
    await expect(page.getByText('Saved for later.', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to cart.', { exact: true })).toBeVisible();

    await page.goto('/cart');
    await expect(page.getByRole('link', { name: 'MT51 Alpha Phone', exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('link', { name: 'MT51 Alpha Phone', exact: true })).toBeVisible();

    const claimReady = page.waitForResponse((response) => response.url().endsWith('/api/customer/wishlist/claim'));
    await login(page, true);
    expect((await claimReady).status()).toBe(200);
    await expect(page.getByText('MT52-E2E-ORDER', { exact: true })).toBeVisible();
    await expect(page.getByText('42 points', { exact: true })).toBeVisible();
    await expect(page.getByText('Saved items', { exact: true }).locator('..')).toContainText('1');
    const cookies = await context.cookies();
    expect(cookies.some(cookie => cookie.name.startsWith('remember_customer_'))).toBe(true);

    await page.goto('/cart');
    const quoteReady = page.waitForResponse((response) => response.url().endsWith('/api/customer/cart/quote'));
    await page.getByRole('button', { name: 'Validate cart' }).click();
    expect((await quoteReady).status()).toBe(200);
    await expect(page.getByText(/Subtotal: PKR 50000\.00/)).toBeVisible();
});

test('MT-5.2 authenticated customer controls alerts and submits an eligible owned review', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);

    // The product button is SSR-rendered before hydration; wait for its mounted CSRF request.
    const csrfReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/auth/csrf-cookie'));
    await page.goto('/products/mt51-alpha-phone');
    const csrfResponse = await csrfReady;
    expect(csrfResponse.status(), 'product page CSRF initialization').toBe(200);
    const alertsCreated = page.waitForResponse((response) =>
        response.url().includes('/api/customer/product-subscriptions/')
        && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Product alerts' }).click();
    const alertResponse = await alertsCreated;
    expect(alertResponse.status(), 'product alert creation status').toBe(201);
    await expect(page.getByText('Product alerts enabled.', { exact: true })).toBeVisible();

    const subscriptionsReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/product-subscriptions')
        && response.request().method() === 'GET'
        && response.status() === 200);
    const eligibilityReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/reviews/eligible')
        && response.status() === 200);
    await page.goto('/account');
    await Promise.all([subscriptionsReady, eligibilityReady]);
    await expect(page.getByRole('heading', { name: 'Product alerts' })).toBeVisible();
    await expect(page.getByText(/MT51 Alpha Phone · back in stock/)).toBeVisible();
    await expect(page.getByText(/MT51 Alpha Phone · price drop/)).toBeVisible();

    const preferencesUpdated = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/notification-preferences')
        && response.request().method() === 'PUT'
        && response.status() === 200);
    await page.getByRole('button', { name: 'Toggle email alerts' }).click();
    await preferencesUpdated;
    await expect(page.getByText('Disabled', { exact: true })).toBeVisible();

    const unsubscribeReady = page.waitForResponse((response) =>
        response.url().includes('/api/customer/product-subscriptions/')
        && response.request().method() === 'DELETE'
        && response.status() === 200);
    const subscriptionsReloaded = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/product-subscriptions')
        && response.request().method() === 'GET'
        && response.status() === 200);
    await page.getByRole('button', { name: 'Unsubscribe' }).first().click();
    await Promise.all([unsubscribeReady, subscriptionsReloaded]);
    await expect(page.getByRole('button', { name: 'Unsubscribe' })).toHaveCount(1);

    const review = page.getByRole('heading', { name: 'Review a purchase' }).locator('..');
    await review.getByRole('combobox').first().selectOption({ label: 'MT52-E2E-ORDER · MT51 Alpha Phone' });
    await review.getByRole('combobox').nth(1).selectOption('5');
    await review.getByPlaceholder('Review title (optional)').fill('Excellent');
    await review.getByPlaceholder('Your review').fill('Verified browser purchase review.');
    const reviewSubmitted = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/reviews')
        && response.request().method() === 'POST'
        && response.status() === 201);
    await review.getByRole('button', { name: 'Submit review' }).click();
    await reviewSubmitted;
    await expect(page.getByText('Review submitted for moderation.', { exact: true })).toBeVisible();
});

test('MT-5.2 inactive commerce prunes cart but preserves authenticated historical account', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);
    state('digital_only');

    try {
        await page.goto('/');
        await expect(page.getByRole('link', { name: 'Cart', exact: true })).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Account', exact: true })).toBeVisible();
        const cart = await page.goto('/cart');
        expect(cart?.status()).toBe(404);
        await page.goto('/account');
        await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
        await expect(page.getByText('MT52-E2E-ORDER', { exact: true })).toBeVisible();
    } finally {
        state('hybrid');
    }
});
