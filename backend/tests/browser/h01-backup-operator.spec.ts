import {expect, test} from '@playwright/test';
import {releaseSyntheticAdminSession} from './synthetic-admin-session';

test.afterEach(async ({page}) => { await releaseSyntheticAdminSession(page); });

async function signIn(page: import('@playwright/test').Page, email: string) {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

test('H01 original backup history/download/local-only delete in real protected Admin UI', async ({page}) => {
    await page.setViewportSize({width: 390, height: 844});
    await signIn(page, 'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/settings/integrations'))?.status()).toBe(200);
    const history = page.getByTestId('backup-operator-history');
    await expect(history).toBeVisible();
    await history.getByRole('button', {name: 'Show backup history'}).click();
    const row = history.locator('[data-testid^="backup-record-"]');
    await expect(row).toHaveCount(1);
    await expect(row).toContainText('completed');
    const downloadPromise = page.waitForEvent('download');
    await row.getByRole('link', {name: 'Download local backup'}).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/^h01-browser-[0-9a-f-]+\.backup$/);
    page.once('dialog', dialog => dialog.accept());
    const removed = page.waitForResponse(r => r.url().match(/\/integrations\/google_drive\/backups\/\d+$/)
        && r.request().method() === 'DELETE');
    await row.getByRole('button', {name: 'Delete local copy'}).click();
    expect((await removed).status()).toBe(200);
    await expect(page.getByRole('status')).toContainText('Local backup copy deleted');
    await history.getByRole('button', {name: 'Show backup history'}).click();
    await expect(history.locator('[data-testid^="backup-record-"]')).toHaveCount(1);
    await expect(history.getByRole('link', {name: 'Download local backup'})).toHaveCount(0);
    await expect(history.getByRole('button', {name: 'Delete local copy'})).toHaveCount(0);
    await expect(page.locator('body')).toHaveJSProperty('scrollWidth', 390);
    expect((await page.goto('/internal/admin/platform'))?.status()).toBe(200);
    await expect(page.getByRole('heading', {name: 'Platform Administration'})).toBeVisible();
});

test('H01 unprivileged salesperson cannot read the backup UI or history API', async ({page}) => {
    await signIn(page, 'e2e-sales@example.invalid');
    const url='/internal/admin/integrations/google_drive/backups';
    expect((await page.goto('/internal/admin/settings/integrations'))?.status()).toBe(403);
    expect((await page.goto(url))?.status()).toBe(403);
});
