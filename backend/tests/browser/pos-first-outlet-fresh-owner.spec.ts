import { expect, test } from '@playwright/test';

const email = 'mt75-fresh-owner@example.invalid';
const password = 'SyntheticFreshOwner123!';

test('fresh protected owner creates, configures and explicitly enters the first outlet', async ({ page }) => {
    test.setTimeout(65_000);
    await page.goto('/internal/admin/pos/login');
    await page.getByTestId('login-email').fill(email);
    await page.getByTestId('login-password').fill(password);
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');

    await expect(page.getByTestId('member-name')).toHaveText('MT75 Fresh Protected Owner');
    await expect(page.getByTestId('outlet-select')).toHaveCount(0);
    await expect(page.getByTestId('no-pos-access')).toBeVisible();
    await expect(page.getByTestId('nav-inventory')).toHaveCount(0);
    const denied = await page.request.get('/internal/admin/pos/workspace/inventory');
    expect(denied.status()).toBe(403);

    await page.getByTestId('manage-outlets-link').click();
    await page.waitForURL('**/internal/admin/outlet-management');
    await expect(page.getByText('001 · MT75 Fresh First Outlet')).toHaveCount(0);
    await page.getByTestId('outlet-owner-password').fill(password);
    await page.getByTestId('outlet-new-name').fill('MT75 Fresh First Outlet');
    await page.getByTestId('outlet-new-address').fill('Target-only synthetic address');
    const created = page.waitForResponse(response => response.url().endsWith('/internal/admin/outlet-management')
        && response.request().method() === 'POST');
    await page.getByTestId('outlet-create').click();
    expect((await created).status()).toBe(201);
    await expect(page.getByText('001 · MT75 Fresh First Outlet')).toBeVisible();

    await page.getByTestId('outlet-manage-profile-001').click();
    await expect(page.getByTestId('outlet-profile-editor')).toBeVisible();
    await page.getByTestId('outlet-profile-business_legal_name').fill('MT75 Fresh Legal Identity');
    await page.getByTestId('outlet-profile-business_phone').fill('+92 300 0000000');
    await page.getByTestId('outlet-profile-business_hours').fill('Mon-Sat 10:00-20:00');
    await page.getByTestId('outlet-managed-password').fill(password);
    await page.getByTestId('outlet-profile-save').click();
    await expect(page.getByTestId('outlet-profile-message')).toHaveText('Outlet profile saved.');
    await expect(page.getByTestId('outlet-profile-code')).toHaveText('001');

    await page.getByRole('link', { name: 'Back to POS' }).click();
    await page.waitForURL('**/internal/admin/pos');
    await expect(page.getByTestId('outlet-select')).toHaveValue('');
    await page.getByTestId('outlet-select').selectOption({ label: 'MT75 Fresh First Outlet' });
    await page.waitForURL('**/internal/admin/pos');
    await expect(page.getByTestId('outlet-select')).toHaveValue(/.+/);
    await page.getByRole('link', { name: 'Inventory', exact: true }).click();
    await page.waitForURL('**/internal/admin/pos/workspace/inventory');
    await expect(page.getByTestId('workspace-inventory')).toBeVisible();
    await expect(page.getByTestId('outlet-profile-code')).toHaveCount(0);

    await page.getByPlaceholder('Product name').fill('MT75 Fresh Accessory');
    await page.getByTestId('product-category').selectOption({ label: 'Accessories' });
    await page.getByPlaceholder('Purchase price').fill('100.00');
    await page.getByPlaceholder('Sale price').fill('150.00');
    await page.getByTestId('product-save').click();
    const product = page.getByRole('button', { name: /MT75 Fresh Accessory/ });
    await expect(product).toContainText('Qty 0');

    const receive = page.getByRole('heading', { name: 'Receive stock' }).locator('xpath=ancestor::section[1]');
    await receive.locator('select').nth(0).selectOption({ label: 'MT75 Fresh Accessory' });
    await receive.getByPlaceholder('Qty').fill('3');
    await receive.getByPlaceholder('Unit cost').fill('100.00');
    await receive.locator('select').nth(1).selectOption({ label: 'Supplier' });
    await receive.getByPlaceholder('Business / seller name').fill('MT75 Synthetic Supplier');
    await receive.getByPlaceholder('03XXXXXXXXX').fill('03000000000');
    await receive.getByPlaceholder('Address').fill('Target-only supplier address');
    await page.getByTestId('acquire-submit').click();
    await expect(page.getByRole('button', { name: /MT75 Fresh Accessory/ })).toContainText('Qty 3');

    await page.getByTestId('logout').click();
    await page.waitForURL('**/internal/admin/pos/login');
});
