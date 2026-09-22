import { expect, Page, test } from '@playwright/test';
import { releaseSyntheticAdminSession } from './synthetic-admin-session';

const paymentStatusPath = '**/internal/admin/website/payment-channels';
const paymentStatusLink = 'Payment channel status (read-only)';

async function loginToPlatform(page: Page) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-platform@example.invalid');
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    await page.goto('/internal/admin/platform');
    await expect(page.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();
}

test.afterEach(async ({ page }) => {
    await releaseSyntheticAdminSession(page);
});

test('W04 Admin navigation exposes read-only payment status for an authorized status response', async ({ page }) => {
    await page.route(paymentStatusPath, async route => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) });
    });
    await loginToPlatform(page);
    await expect(page.getByRole('navigation', { name: 'Payment administration' }).getByRole('link', { name: paymentStatusLink }))
        .toHaveAttribute('href', '/internal/admin/website/payment-settings');
});

test('W04 Admin navigation never shows payment status when the status endpoint denies permission', async ({ page }) => {
    await page.route(paymentStatusPath, async route => {
        await route.fulfill({ status: 403, contentType: 'application/json', body: '{"message":"Forbidden"}' });
    });
    await loginToPlatform(page);
    await expect(page.getByRole('navigation', { name: 'Payment administration' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: paymentStatusLink })).toHaveCount(0);
});
