import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';
import { releaseSyntheticAdminSession } from './synthetic-admin-session';

const pagePath = '/internal/admin/website/payment-settings';
const draftPath = `${pagePath}/presentation/drafts`;

test.afterEach(async ({ page }) => { await releaseSyntheticAdminSession(page); });
test.afterAll(() => {
    if (process.env.CI === 'true' || process.env.MT75_FIRST_OUTLET_E2E_ENABLED === '1') {
        execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\W04PaymentPresentationE2eCleanupSeeder', '--env=testing', '--force'], {
            cwd: process.cwd(), stdio: 'inherit', env: process.env,
        });
    }
});

test('W04 authorized Admin publishes new-only nonsecret payment presentation with external channels off', async ({ page }) => {
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-protected-owner@example.invalid');
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
    // Wait for the authenticated POS shell to finish its login navigation before
    // starting a second full navigation to the payment settings page.
    await expect(page.getByTestId('logout')).toBeVisible();
    expect((await page.goto(pagePath))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Payment labels, instructions and COD limits' })).toBeVisible();
    const codGroup = page.getByRole('group', { name: 'COD' });
    await codGroup.getByRole('textbox', { name: 'Customer-facing name' }).fill('Pay on receipt');
    await codGroup.getByRole('textbox', { name: 'Customer instructions' }).fill('Prepare cash for delivery.');
    await page.getByRole('spinbutton', { name: 'COD minimum (PKR)' }).fill('100');
    await page.getByRole('spinbutton', { name: 'COD maximum (PKR)' }).fill('500');
    const save = page.waitForResponse(response => response.request().method() === 'POST'
        && new URL(response.url()).pathname === draftPath);
    await page.getByRole('button', { name: 'Save presentation draft' }).click();
    const saved = await save;
    expect(saved.status()).toBe(201);
    const draft = await saved.json() as { data: { id: number; version: number } };
    expect(draft.data.id).toBeGreaterThan(0);
    await expect(page.getByText(`Draft v${draft.data.version} is not yet live.`)).toBeVisible();
    await expect(page.getByRole('rowheader', { name: 'Cash on Delivery' })).toBeVisible();
    const publish = page.waitForResponse(response => response.request().method() === 'POST'
        && new URL(response.url()).pathname === `${draftPath}/${draft.data.id}/publish`);
    await page.getByRole('button', { name: 'Publish presentation draft' }).click();
    expect((await publish).status()).toBe(200);
    await expect(page.getByRole('rowheader', { name: 'Pay on receipt' })).toBeVisible();
    await expect(page.getByRole('row').filter({ has: page.getByRole('rowheader', { name: 'JazzCash' }) })).toContainText('Unavailable');
    await page.reload();
    await expect(page.getByRole('group', { name: 'COD' }).getByRole('textbox', { name: 'Customer instructions' })).toHaveValue('Prepare cash for delivery.');
    await expect(page.getByRole('spinbutton', { name: 'COD minimum (PKR)' })).toHaveValue('100.00');
    await expect(page.getByRole('spinbutton', { name: 'COD maximum (PKR)' })).toHaveValue('500.00');
});