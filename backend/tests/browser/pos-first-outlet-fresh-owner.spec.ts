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

    await page.goto('/internal/admin/platform');
    await page.getByRole('button', { name: 'Documents, payments & retail' }).click();
    const destinations = page.getByRole('heading', { name: 'POS Payment Destinations' }).locator('xpath=ancestor::section[1]');
    await destinations.getByPlaceholder('Display name').fill('MT75 Fresh Bank');
    await destinations.getByPlaceholder('Masked identifier').fill('****7500');
    const destinationCreated = page.waitForResponse(response => response.url().endsWith('/internal/admin/platform/payment-destinations')
        && response.request().method() === 'POST');
    await destinations.getByRole('button', { name: 'Add destination' }).click();
    expect((await destinationCreated).status()).toBe(200);
    await expect(destinations.locator('input').nth(2)).toHaveValue('MT75 Fresh Bank');

    await page.goto('/internal/admin/pos/workspace/sales');
    await page.getByRole('button', { name: /MT75 Fresh Accessory/ }).click();
    const sale = page.getByRole('heading', { name: 'Sale & payment' }).locator('xpath=ancestor::section[1]');
    await sale.getByPlaceholder('Customer name (optional)').fill('MT75 Fresh Customer');
    await sale.getByPlaceholder('03XXXXXXXXX').fill('03001112222');
    await sale.getByPlaceholder('Customer email (optional)').fill('mt75-fresh-customer@example.invalid');
    await sale.getByRole('button', { name: 'Add Payment' }).click();
    await sale.getByPlaceholder('Amount').fill('150.00');
    await sale.getByPlaceholder('Safe reference').fill('MT75-FRESH-BANK-001');
    await page.getByTestId('server-totals').click();
    await expect(page.getByTestId('authoritative-totals')).toContainText('Remaining');
    await expect(page.getByTestId('authoritative-totals')).toContainText('PKR 0.00');
    await page.getByTestId('finalize-sale').click();
    await expect(page.getByTestId('sale-result')).toContainText('Sale complete:');
    await expect(page.getByTestId('sale-result')).toContainText('Final PKR 150.00');

    await page.getByTestId('logout').click();
    await page.waitForURL('**/internal/admin/pos/login');
});
