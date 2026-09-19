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


test('assigned Admin edits outlet profile through protected POS workspace without changing outlet identity', async ({ page }) => {
    await page.setViewportSize({width:1280,height:900});
    await login(page,'e2e-platform@example.invalid');
    await expect(page.locator('aside').getByTestId('nav-profile')).toBeVisible();
    const response=await page.goto('/internal/admin/pos/workspace/profile');
    expect(response?.status()).toBe(200);
    await expect(page.getByTestId('outlet-profile-editor')).toBeVisible();
    await expect(page.getByTestId('outlet-profile-code')).toHaveText('E41');
    await expect(page.getByTestId('outlet-profile-name')).toHaveValue('E2E Sales Outlet');
    await page.getByTestId('outlet-profile-name').fill('E2E Verified Profile');
    await page.getByTestId('outlet-profile-business_phone').fill('+92 300 1234567');
    const saved=page.waitForResponse(r=>r.url().endsWith('/internal/admin/pos/outlet-profile')&&r.request().method()==='PATCH');
    await page.getByTestId('outlet-profile-save').click();
    expect((await saved).status()).toBe(200);
    await expect(page.getByTestId('outlet-profile-message')).toHaveText('Outlet profile saved.');
    await expect(page.getByTestId('outlet-profile-code')).toHaveText('E41');
    await expect(page.getByTestId('outlet-profile-name')).toHaveValue('E2E Verified Profile');
    await page.reload();
    await expect(page.getByTestId('outlet-profile-name')).toHaveValue('E2E Verified Profile');
});

test('protected Admin creates and archives an unused outlet in actual UI; operator denied', async ({page,browser}) => {
    await page.setViewportSize({width:1280,height:900});
    await login(page,'e2e-protected-owner@example.invalid');
    await expect(page.getByTestId('manage-outlets-link')).toBeVisible();
    const entry=await page.goto('/internal/admin/outlet-management');
    expect(entry?.status()).toBe(200);
    await expect(page.getByRole('heading',{name:'Outlet management'})).toBeVisible();
    await page.getByTestId('outlet-owner-password').fill(password);
    await page.getByTestId('outlet-new-name').fill('E2E Lifecycle Outlet');
    await page.getByTestId('outlet-new-address').fill('Synthetic non-production address');
    const created=page.waitForResponse(r=>r.url().endsWith('/internal/admin/outlet-management')&&r.request().method()==='POST');
    await page.getByTestId('outlet-create').click();
    expect((await created).status()).toBe(201);
    await expect(page.getByText('E2E Lifecycle Outlet',{exact:false})).toBeVisible();
    await expect(page.getByRole('status')).toHaveText('Outlet change saved.');
    const row=page.getByText('E2E Lifecycle Outlet',{exact:false}).locator('..').locator('..');
    await page.getByTestId('outlet-owner-password').fill(password);
    const archived=page.waitForResponse(r=>r.url().includes('/internal/admin/outlet-management/')&&r.url().endsWith('/archive')&&r.request().method()==='POST');
    await row.getByRole('button',{name:'Archive empty outlet'}).click();
    expect((await archived).status()).toBe(200);
    await expect(page.getByText('E2E Lifecycle Outlet',{exact:false})).toContainText('E2E Lifecycle Outlet');
    await expect(page.getByText(/E2E Lifecycle Outlet.*archived/)).toBeVisible();
    const guest=await browser.newContext();
    try {const operator=await guest.newPage();await login(operator,'e2e-sales@example.invalid');
        await expect(operator.getByTestId('manage-outlets-link')).toHaveCount(0);
        expect((await operator.goto('/internal/admin/outlet-management'))?.status()).toBe(403);
        await operator.goto('/internal/admin/pos');
        await operator.getByTestId('logout').click();
        await operator.waitForURL('**/internal/admin/pos/login');
    } finally {await guest.close();}
});


test('Team Member account and recovery UI are accessible only through the approved Admin realm', async ({page}) => {
    await page.goto('/internal/admin/forgot-password');
    await expect(page.getByRole('heading',{name:'Team Member account recovery'})).toBeVisible();
    await page.getByTestId('recovery-email').fill('e2e-sales@example.invalid');
    const disabled=page.waitForResponse(r=>r.url().endsWith('/internal/admin/auth/forgot-password')&&r.request().method()==='POST');
    await page.getByTestId('recovery-submit').click();
    expect((await disabled).status()).toBe(503);
    await expect(page.getByTestId('recovery-message')).toHaveText('Email recovery is unavailable. Contact your administrator.');
    const unauthenticated=await page.goto('/internal/admin/manage-account');
    expect(unauthenticated?.status()).toBe(401);
    await page.goto('/internal/admin/reset-password?email=e2e-sales%40example.invalid&token=synthetic-invalid');
    await expect(page.getByRole('heading',{name:'Set a new password'})).toBeVisible();
    await expect(page.getByTestId('recovery-email')).toHaveValue('e2e-sales@example.invalid');
    await login(page,'e2e-sales@example.invalid');
    await page.getByTestId('my-account-link').click();
    await page.waitForURL('**/internal/admin/manage-account');
    await expect(page.getByTestId('account-name')).toHaveText('E2E Salesperson');
    await expect(page.getByTestId('account-email')).toHaveText('e2e-sales@example.invalid');
    await page.getByTestId('account-current').fill('incorrect-password');
    await page.getByTestId('account-new').fill('SyntheticUpdatedPass123!');
    await page.getByTestId('account-confirm').fill('SyntheticUpdatedPass123!');
    const rejected=page.waitForResponse(r=>r.url().endsWith('/internal/admin/auth/password')&&r.request().method()==='PATCH');
    await page.getByTestId('account-change').click();
    expect((await rejected).status()).toBe(422);
    await expect(page.getByRole('alert')).toHaveText('The request could not be completed.');
    await page.getByTestId('account-current').fill(password);
    const accepted=page.waitForResponse(r=>r.url().endsWith('/internal/admin/auth/password')&&r.request().method()==='PATCH');
    await page.getByTestId('account-change').click();
    expect((await accepted).status()).toBe(200);
    await page.waitForURL('**/internal/admin/pos/login');
    await page.getByTestId('login-email').fill('e2e-sales@example.invalid');
    await page.getByTestId('login-password').fill(password);
    const old=page.waitForResponse(r=>r.url().endsWith('/internal/admin/auth/login')&&r.request().method()==='POST');
    await page.getByTestId('login-submit').click();
    expect((await old).status()).toBe(422);
    await page.getByTestId('login-password').fill('SyntheticUpdatedPass123!');
    const fresh=page.waitForResponse(r=>r.url().endsWith('/internal/admin/auth/login')&&r.request().method()==='POST');
    await page.getByTestId('login-submit').click();
    expect((await fresh).status()).toBe(200);
    await page.waitForURL('**/internal/admin/pos');
});
