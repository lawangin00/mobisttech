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

async function postAndObserve(page: Page, pathname: string, status: number, click: () => Promise<void>) {
    const pending = page.waitForResponse(result => result.request().method() === 'POST'
        && new URL(result.url()).pathname === pathname);
    await click();
    const response = await pending;
    // A generic 303 (including a middleware redirect) cannot prove a policy
    // write; require the authenticated JSON contract and its saved revision.
    expect(response.status(), `${pathname}: ${await response.text()}`).toBe(status);
    expect(response.headers()['content-type']).toContain('application/json');
    const body = await response.json() as { data?: { id?: number; version?: number; cod_enabled?: boolean } };
    expect(body.data?.id).toBeGreaterThan(0);
    expect(body.data?.version).toBeGreaterThan(0);
    return body.data;
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
    const disabledDraft = await postAndObserve(page, draftPath, 201,
        () => page.getByRole('button', { name: 'Save policy draft' }).click());
    expect(disabledDraft?.cod_enabled).toBe(false);
    await expect(page.getByText(/^Latest draft v/)).toContainText('COD disabled (not yet live)');
    await expect(current).toContainText('COD enabled');
    await page.reload();
    await expect(page.getByText(/^Latest draft v/)).toContainText('COD disabled (not yet live)');
    await expect(toggle).not.toBeChecked();

    const firstVersion = await page.getByText(/^Latest draft v/).textContent();
    await expect(page.getByRole('button', { name: 'Publish latest COD draft' })).toHaveCount(1);
    const disabledPublished = await postAndObserve(page, `${draftPath}/${disabledDraft?.id}/publish`, 200,
        () => page.getByRole('button', { name: 'Publish latest COD draft' }).click());
    expect(disabledPublished?.cod_enabled).toBe(false);
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
    const rollbackDraft = await postAndObserve(page, draftPath, 201,
        () => page.getByRole('button', { name: 'Save policy draft' }).click());
    expect(rollbackDraft?.cod_enabled).toBe(true);
    expect(rollbackDraft?.version).toBeGreaterThan(disabledDraft?.version ?? 0);
    await expect(page.getByText(/^Latest draft v/)).toContainText('COD enabled (not yet live)');
    await expect(page.getByText(/^Latest draft v/)).not.toHaveText(firstVersion ?? '');
    await expect(current).toContainText('COD disabled');
    const rollbackPublished = await postAndObserve(page, `${draftPath}/${rollbackDraft?.id}/publish`, 200,
        () => page.getByRole('button', { name: 'Publish latest COD draft' }).click());
    expect(rollbackPublished?.cod_enabled).toBe(true);
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
