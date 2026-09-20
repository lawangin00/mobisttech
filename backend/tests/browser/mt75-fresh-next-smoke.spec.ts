import { expect, test } from '@playwright/test';

const email = 'mt75-fresh-owner@example.invalid';
const password = 'SyntheticFreshOwner123!';

test('MT75 fresh publication reaches real Next.js product and guest cart', async ({ page }) => {
    test.setTimeout(125_000);
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
    await page.getByRole('combobox', { name: 'Warranty type' }).selectOption('shop_warranty');
    await page.getByRole('spinbutton', { name: 'Warranty duration' }).fill('30');
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
    const modeSection = page.getByRole('heading', { name: 'Website operating mode' }).locator('xpath=ancestor::section[1]');
    await modeSection.getByRole('button', { name: 'Save mode draft' }).click();
    await modeSection.getByRole('button', { name: 'Publish', exact: true }).first().click();
    await expect(modeSection).toContainText('Current: hybrid');
    await page.goto('/internal/admin/pos/workspace/inventory');
    await page.getByRole('button', { name: 'Website listing' }).click();
    await expect(page.getByTestId('website-listing-editor')).toBeVisible();
    await page.getByTestId('website-listing-publish').click();
    await expect(page.getByTestId('website-listing-editor')).toHaveCount(0);
    const publicProduct = await page.request.get('http://127.0.0.1:18080/api/v1/catalogue/products/mt75-fresh-accessory');
    expect(publicProduct.status()).toBe(200);
    const publicData = await publicProduct.json() as { data: { name: string; price: string; availability: {quantity:number} } };
    expect(publicData.data).toMatchObject({name:'MT75 Fresh Accessory',price:'150.00',availability:{quantity:3}});
    expect(JSON.stringify(publicData.data)).not.toMatch(/purchase_price|customer_phone|customer_email|imei|object_key/);

    const displayed = await page.goto('http://127.0.0.1:13000/products/mt75-fresh-accessory');
    expect(displayed?.status()).toBe(200);
    await expect(page.getByRole('heading',{name:'MT75 Fresh Accessory'})).toBeVisible();
    await expect(page.getByText('Rs 150',{exact:true})).toBeVisible();
    await expect(page.locator('main').getByText('3 available').first()).toBeVisible();
    const html=(await page.content()).toLowerCase();
    for(const privateField of ['purchase_price','customer_cnic','customer_phone','imei','object_key']) expect(html).not.toContain(privateField);
    await page.getByRole('button',{name:'Add to cart'}).click();
    await expect(page.getByText('Added to cart.',{exact:true})).toBeVisible();
    await page.goto('http://127.0.0.1:13000/cart');
    await expect(page.getByRole('link',{name:'MT75 Fresh Accessory'})).toBeVisible();
});
