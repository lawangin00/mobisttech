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

test('MT-5.2 customer account keeps cart ownership history engagement and session boundaries', async ({ page, context }) => {
    test.setTimeout(120_000);

    await page.goto('/products/mt51-alpha-phone');
    await page.getByRole('button', { name: 'Save for later' }).click();
    await expect(page.getByText('Saved for later.', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Add to cart' }).click();
    await expect(page.getByText('Added to cart.', { exact: true })).toBeVisible();

    await page.goto('/cart');
    await expect(page.getByRole('heading', { name: 'Cart', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'MT51 Alpha Phone', exact: true })).toBeVisible();
    await page.reload();
    await expect(page.getByRole('link', { name: 'MT51 Alpha Phone', exact: true })).toBeVisible();

    await page.goto('/account');
    await page.getByLabel('Email').fill('mt52-customer@example.invalid');
    await page.getByLabel('Password').fill('SyntheticPass123!');
    await page.getByLabel('Remember me on this device').check();
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();

    await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
    await expect(page.getByText('MT52-E2E-ORDER', { exact: true })).toBeVisible();
    await expect(page.getByText('42 points', { exact: true })).toBeVisible();
    const savedItems = page.getByText('Saved items', { exact: true }).locator('..');
    await expect(savedItems).toContainText('1');
    const cookies = await context.cookies();
    expect(cookies.some(cookie => cookie.name.startsWith('remember_customer_'))).toBe(true);

    await page.goto('/cart');
    await page.getByRole('button', { name: 'Validate cart' }).click();
    await expect(page.getByText(/Subtotal: PKR 50000\.00/)).toBeVisible();

    await page.goto('/products/mt51-alpha-phone');
    await page.getByRole('button', { name: 'Product alerts' }).click();
    await expect(page.getByText('Product alerts enabled.', { exact: true })).toBeVisible();

    await page.goto('/account');
    await expect(page.getByRole('heading', { name: 'Product alerts' })).toBeVisible();
    await expect(page.getByText(/MT51 Alpha Phone · back in stock/)).toBeVisible();
    await expect(page.getByText(/MT51 Alpha Phone · price drop/)).toBeVisible();
    await page.getByRole('button', { name: 'Toggle email alerts' }).click();
    await expect(page.getByText('Disabled', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Unsubscribe' }).first().click();
    await expect(page.getByRole('button', { name: 'Unsubscribe' })).toHaveCount(1);

    const review = page.getByRole('heading', { name: 'Review a purchase' }).locator('..');
    await review.getByRole('combobox').first().selectOption({ label: /MT52-E2E-ORDER · MT51 Alpha Phone/ });
    await review.getByRole('combobox').nth(1).selectOption('5');
    await review.getByPlaceholder('Review title (optional)').fill('Excellent');
    await review.getByPlaceholder('Your review').fill('Verified browser purchase review.');
    await review.getByRole('button', { name: 'Submit review' }).click();
    await expect(page.getByText('Review submitted for moderation.', { exact: true })).toBeVisible();

    state('digital_only');
    await page.goto('/');
    await expect(page.getByRole('link', { name: 'Cart', exact: true })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Account', exact: true })).toBeVisible();
    const cart = await page.goto('/cart');
    expect(cart?.status()).toBe(404);
    await page.goto('/account');
    await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
    await expect(page.getByText('MT52-E2E-ORDER', { exact: true })).toBeVisible();
});
