import { execFileSync } from 'node:child_process';
import { expect, type Page, test } from '@playwright/test';
import { releaseSyntheticAdminSession } from './synthetic-admin-session';

const settingsPath = '/internal/admin/website/payment-settings';
const draftPath = `${settingsPath}/drafts`;

async function login(page: Page, email: string) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

async function postAndObserve(page: Page, pathname: string, click: () => Promise<void>) {
    const response = page.waitForResponse(result => result.request().method() === 'POST'
        && new URL(result.url()).pathname === pathname);
    await click();
    expect((await response).status()).toBe(303);
}

test.afterEach(async ({ page }) => {
    await releaseSyntheticAdminSession(page);
});

test.afterAll(() => {
    if (process.env.CI === 'true') {
        execFileSync('php', [
            'artisan', 'db:seed', '--class=Database\\Seeders\\W04CodPolicyE2eCleanupSeeder',
            '--env=testing', '--force',
        ], { cwd: process.cwd(), stdio: 'inherit' });
    }
});

test('MT-7.5 W04 protected Admin COD draft publish reload and compensating rollback affect only COD', async ({ page }) => {
    await login(page, 'e2e-protected-owner@example.invalid');
    expect((await page.goto(settingsPath))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Website payment channels' })).toBeVisible();
    const current = page.getByText(/^Current checkout:/);
    const cod = page.getByRole('row').filter({ has: page.getByRole('rowheader', { name: 'Cash on Delivery' }) });
    const external = page.getByRole('row').filter({ has: page.getByRole('rowheader', { name: 'JazzCash' }) });
    const toggle = page.getByRole('checkbox', { name: 'Offer Cash on Delivery at checkout' });
    await expect(current).toContainText('COD enabled');
    await expect(cod).toContainText('Available');
    await expect(external).toContainText('Unavailable');

    await toggle.uncheck();
    await postAndObserve(page, draftPath, () => page.getByRole('button', { name: 'Save policy draft' }).click());
    await expect(page.getByText(/^Latest draft v/)).toContainText('COD disabled (not yet live)');
    await expect(current).toContainText('COD enabled');
    await page.reload();
    await expect(page.getByText(/^Latest draft v/)).toContainText('COD disabled (not yet live)');
    await expect(toggle).not.toBeChecked();

    const firstVersion = await page.getByText(/^Latest draft v/).textContent();
    const draftId = await page.locator('button').filter({ hasText: 'Publish latest COD draft' }).count();
    expect(draftId).toBe(1);
    const publishRequest = page.waitForResponse(result => result.request().method() === 'POST'
        && new URL(result.url()).pathname.startsWith(`${draftPath}/`)
        && new URL(result.url()).pathname.endsWith('/publish'));
    await page.getByRole('button', { name: 'Publish latest COD draft' }).click();
    expect((await publishRequest).status()).toBe(303);
    await expect(current).toContainText('COD disabled');
    await expect(page.getByText(/^Latest draft v/)).toHaveCount(0);
    await expect(cod).toContainText('Unavailable');
    await expect(external).toContainText('Unavailable');
    await page.reload();
    await expect(current).toContainText('COD disabled');
    await expect(toggle).not.toBeChecked();

    // A new explicit opposite-value draft is the compensating rollback; no old
    // published revision is replayed or external payment adapter activated.
    await toggle.check();
    await postAndObserve(page, draftPath, () => page.getByRole('button', { name: 'Save policy draft' }).click());
    await expect(page.getByText(/^Latest draft v/)).toContainText('COD enabled (not yet live)');
    await expect(page.getByText(/^Latest draft v/)).not.toHaveText(firstVersion ?? '');
    await expect(current).toContainText('COD disabled');
    const rollbackResponse = page.waitForResponse(result => result.request().method() === 'POST'
        && new URL(result.url()).pathname.startsWith(`${draftPath}/`)
        && new URL(result.url()).pathname.endsWith('/publish'));
    await page.getByRole('button', { name: 'Publish latest COD draft' }).click();
    expect((await rollbackResponse).status()).toBe(303);
    await expect(current).toContainText('COD enabled');
    await expect(cod).toContainText('Available');
    await expect(external).toContainText('Unavailable');
    await page.reload();
    await expect(current).toContainText('COD enabled');
    await expect(page.getByText(/^Latest draft v/)).toHaveCount(0);
});

test('MT-7.5 W04 Admin without payment management permission cannot open COD policy or channel status', async ({ page }) => {
    await login(page, 'e2e-platform@example.invalid');
    expect((await page.goto(settingsPath))?.status()).toBe(403);
    expect((await page.goto('/internal/admin/website/payment-channels'))?.status()).toBe(403);
});
