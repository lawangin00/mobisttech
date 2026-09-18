import { expect, Page, test } from '@playwright/test';

const password = 'SyntheticPass123!';

async function login(page: Page, email: string) {
    await page.goto('/internal/admin/pos/login');
    await expect(page.getByRole('heading', { name: 'Team Member sign in' })).toBeVisible();
    await expect(page.getByRole('checkbox')).toHaveCount(0);
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');
}

test('desktop salesperson sees only authorized POS navigation and direct routes stay protected', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page, 'e2e-sales@example.invalid');

    await expect(page.getByTestId('member-name')).toHaveText('E2E Salesperson');
    await expect(page.getByText('E2E Salesperson', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sales', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Inventory', exact: true })).toHaveCount(0);
    await expect(page.getByTestId('workspace-cards')).toBeVisible();

    const denied = await page.goto('/internal/admin/pos/workspace/inventory');
    expect(denied?.status()).toBe(403);

    const allowed = await page.goto('/internal/admin/pos/workspace/sales');
    expect(allowed?.status()).toBe(200);
    await expect(page.getByTestId('workspace-sales')).toBeVisible();
    await expect(page.getByTestId('server-totals')).toBeVisible();

    await page.goto('/internal/admin/pos');
    await page.getByTestId('logout').click();
    await page.waitForURL('**/internal/admin/pos/login');
    await expect(page.getByRole('heading', { name: 'Team Member sign in' })).toBeVisible();
});

test('mobile inventory manager selects outlet and receives responsive permission-scoped navigation', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await login(page, 'e2e-inventory@example.invalid');

    await expect(page.getByTestId('outlet-required')).toBeVisible();
    await expect(page.getByTestId('outlet-select')).toBeVisible();
    const selected = page.waitForResponse((response) =>
        response.url().endsWith('/internal/admin/outlets/select')
        && response.request().method() === 'POST'
        && response.ok()
    );
    await page.getByTestId('outlet-select').selectOption({ label: 'E2E Inventory Outlet' });
    await selected;
    await expect(page.getByTestId('outlet-required')).toHaveCount(0);

    await page.getByText('Menu', { exact: true }).click();
    await expect(page.getByRole('link', { name: 'Inventory', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sales', exact: true })).toHaveCount(0);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);

    const denied = await page.goto('/internal/admin/pos/workspace/sales');
    expect(denied?.status()).toBe(403);

    const allowed = await page.goto('/internal/admin/pos/workspace/inventory');
    expect(allowed?.status()).toBe(200);
    await expect(page.getByTestId('workspace-inventory')).toBeVisible();
    await expect(page.getByTestId('acquire-submit')).toBeVisible();
    await expect(page.getByTestId('outlet-select').locator('option:checked')).toHaveText('E2E Inventory Outlet');

    await page.getByTestId('logout').click();
    await page.waitForURL('**/internal/admin/pos/login');
});
