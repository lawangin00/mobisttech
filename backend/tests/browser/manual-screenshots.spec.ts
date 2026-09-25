import { expect, Page, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';

const password = 'SyntheticPass123!';
const assets = resolve(process.cwd(), '..', 'docs', 'user-manual', 'assets');

test.describe.configure({ mode: 'serial' });
test.setTimeout(120_000);

async function shot(page: Page, name: string) {
    mkdirSync(assets, { recursive: true });
    await page.screenshot({
        path: resolve(assets, name),
        fullPage: false,
        animations: 'disabled',
    });
}

async function resetSession(page: Page) {
    await page.context().clearCookies();
    await page.goto('/internal/admin/pos/login');
}

async function login(page: Page, email: string) {
    await resetSession(page);
    await expect(page.getByRole('heading', { name: 'Team Member sign in' })).toBeVisible();
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

test('MT-7.6 manual screenshot batch A - final Admin/POS surfaces', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });

    await resetSession(page);
    await expect(page.getByRole('heading', { name: 'Team Member sign in' })).toBeVisible();
    await shot(page, 'S01_admin-sign-in.png');

    await login(page, 'e2e-sales@example.invalid');
    await expect(page.getByTestId('member-name')).toHaveText('E2E Salesperson');
    await expect(page.getByRole('link', { name: 'Sales', exact: true })).toBeVisible();
    await shot(page, 'S02_pos-home.png');

    const productId = '11111111-1111-4111-8111-111111111111';
    const cashId = '22222222-2222-4222-8222-222222222222';
    const cardId = '33333333-3333-4333-8333-333333333333';

    await page.route('**/internal/admin/pos/catalogue**', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            outlet: { id: 'e2e-sales', name: 'E2E Sales Outlet' },
            products: [{
                id: productId, code: 'E2E-MANUAL', name: 'Synthetic Manual Accessory',
                purchase_price: '100.00', sale_price: '200.02', qty: 4, track_imei: false, units: [],
            }],
            page: 1, has_more: false, master_data: [],
            payment_destinations: [
                { public_id: cashId, method: 'cash', display_name: 'Synthetic Cash Drawer' },
                { public_id: cardId, method: 'card', display_name: 'Synthetic Card Terminal' },
            ],
        } }) });
    });
    await page.route('**/internal/admin/pos/sales/quote', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            gross: '200.02', discount: '0.00', payable: '200.02', payments_total: '200.02',
            remaining: '0.00', cash_change: '50.00', promotion_or_loyalty_recalculated_on_finalize: false,
        } }) });
    });

    await login(page, 'e2e-transaction@example.invalid');
    await page.goto('/internal/admin/pos/workspace/sales');
    await expect(page.getByTestId('workspace-sales')).toBeVisible();
    await page.getByRole('button', { name: /Synthetic Manual Accessory/ }).click();

    const salePanel = page.getByRole('heading', { name: 'Sale & payment' }).locator('..');
    await page.getByRole('button', { name: 'Add Payment' }).click();
    await salePanel.getByPlaceholder('Amount').first().fill('200.02');
    await salePanel.getByPlaceholder('Cash tendered').fill('250.02');
    await page.getByTestId('server-totals').click();
    await expect(page.getByTestId('authoritative-totals')).toContainText('PKR 200.02');
    await shot(page, 'S03_sales-payment-editor.png');

    await salePanel.getByPlaceholder('Amount').first().fill('50.02');
    await page.getByRole('button', { name: 'Add Payment' }).click();
    await salePanel.locator('select').nth(1).selectOption(cardId);
    await salePanel.getByPlaceholder('Amount').nth(1).fill('150.00');
    await salePanel.getByPlaceholder('Cash tendered').fill('100.02');
    await salePanel.getByPlaceholder('Safe reference').nth(1).fill('SYNTHETIC-REF');
    await page.getByTestId('server-totals').click();
    await expect(page.getByTestId('authoritative-totals')).toContainText('PKR 50.00');
    await shot(page, 'S04_split-tender.png');

    await page.unroute('**/internal/admin/pos/catalogue**');
    await page.unroute('**/internal/admin/pos/sales/quote');

    await login(page, 'e2e-inventory@example.invalid');
    if (await page.getByTestId('outlet-required').count()) {
        const selected = page.waitForResponse((response) =>
            response.url().endsWith('/internal/admin/outlets/select')
            && response.request().method() === 'POST'
            && response.ok()
        );
        await page.getByTestId('outlet-select').selectOption({ label: 'E2E Inventory Outlet' });
        await selected;
    }
    await page.goto('/internal/admin/pos/workspace/inventory');
    await expect(page.getByTestId('workspace-inventory')).toBeVisible();
    await expect(page.getByTestId('acquire-submit')).toBeVisible();
    await shot(page, 'S06_inventory.png');

});

test('MT-7.6 manual screenshot batch A2 - final Platform surfaces', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 1000 });
    await login(page, 'e2e-platform@example.invalid');
    await page.goto('/internal/admin/platform');
    await expect(page.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();

    await page.getByRole('button', { name: 'POS configuration', exact: true }).click();
    await expect(page.getByText('Canonical Business Profile', { exact: true })).toBeVisible();
    await shot(page, 'S12_platform-administration.png');

    await page.getByRole('button', { name: 'Team & integrations', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Team Member administration' })).toBeVisible();
    await shot(page, 'S13_team-members.png');

    await page.getByRole('button', { name: 'Website', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Website operating mode' })).toBeVisible();
    await shot(page, 'S14_website-mode.png');
});
