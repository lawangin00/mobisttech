import { expect, Page, test } from '@playwright/test';
import { releaseSyntheticAdminSession } from './synthetic-admin-session';

test.afterEach(async ({ page }) => {
    await releaseSyntheticAdminSession(page);
});

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

test('MT-7.5 W01 protected owner cannot change own authority and Admin cannot enter Customer realm', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 900 });
    await login(page, 'e2e-protected-owner@example.invalid');

    expect((await page.goto('/internal/admin/platform'))?.status()).toBe(200);
    await page.getByRole('button', { name: 'Team & integrations' }).click();
    const team = page.getByRole('heading', { name: 'Team Member administration' }).locator('..');
    await team.locator('select').selectOption({ label: 'E2E Protected Owner · active' });
    const rejected = page.waitForResponse((response) => response.url().includes('/internal/admin/team-members/')
        && response.request().method() === 'PATCH');
    await team.getByRole('button', { name: 'Save Team Member' }).click();
    expect((await rejected).status()).toBe(403);
    await expect(page.getByRole('alert')).toHaveText('Request failed.');

    const customerRealmStatus = await page.evaluate(async () => (await fetch('/api/v1/account', {
        headers: { Accept: 'application/json' },
    })).status);
    expect(customerRealmStatus).toBe(401);
});

test('desktop salesperson sees only authorized POS navigation and direct routes stay protected', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await login(page, 'e2e-sales@example.invalid');

    await expect(page.locator('aside img')).toHaveAttribute('src', '/brand/mobist-wordmark.svg');
    await expect(page.locator('[data-pos-theme]')).toHaveCSS('background-color', 'rgb(247, 248, 251)');
    expect(await page.locator('[data-pos-theme]').evaluate((node) => getComputedStyle(node).getPropertyValue('--pos-primary').trim())).toBe('#008080');
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

    await expect(page.locator('header img')).toHaveAttribute('src', '/brand/mobist-wordmark.svg');
    await expect(page.locator('[data-pos-theme]')).toHaveCSS('background-color', 'rgb(247, 248, 251)');
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
    // Restore the shared synthetic outlet after verifying profile persistence.
    await page.getByTestId('outlet-profile-name').fill('E2E Sales Outlet');
    const restored=page.waitForResponse(r=>r.url().endsWith('/internal/admin/pos/outlet-profile')&&r.request().method()==='PATCH');
    await page.getByTestId('outlet-profile-save').click();
    expect((await restored).status()).toBe(200);
    await page.reload();
    await expect(page.getByTestId('outlet-profile-name')).toHaveValue('E2E Sales Outlet');
});

test('Full Access edits an unassigned outlet only after explicit password confirmation without membership transfer', async ({page}) => {
    await page.setViewportSize({width:1280,height:900});
    await login(page,'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/outlet-management'))?.status()).toBe(200);
    const target = page.getByTestId('outlet-manage-profile-E42');
    await expect(target).toBeVisible();
    await target.click();
    await expect(page.getByTestId('outlet-profile-code')).toHaveText('E42');
    await expect(page.getByTestId('outlet-profile-name')).toHaveValue('E2E Inventory Outlet');
    await page.getByTestId('outlet-profile-name').fill('E2E Unassigned Profile Reviewed');
    await expect(page.getByTestId('outlet-profile-save')).toBeDisabled();
    await page.getByTestId('outlet-managed-password').fill(password);
    const accepted=page.waitForResponse(r=>r.url().includes('/internal/admin/outlet-management/')&&r.url().endsWith('/profile')&&r.request().method()==='PATCH');
    await page.getByTestId('outlet-profile-save').click();
    expect((await accepted).status()).toBe(200);
    await expect(page.getByTestId('outlet-profile-message')).toHaveText('Outlet profile saved.');
    await expect(page.getByTestId('outlet-profile-code')).toHaveText('E42');
    await page.reload();
    await page.getByTestId('outlet-manage-profile-E42').click();
    await expect(page.getByTestId('outlet-profile-name')).toHaveValue('E2E Unassigned Profile Reviewed');
    const assigned=await page.evaluate(async()=>{const r=await fetch('/internal/admin/outlets',{headers:{Accept:'application/json'}});return r.ok?(await r.json()).data:[];}) as Array<{name:string}>;
    expect(assigned.some(outlet=>outlet.name==='E2E Unassigned Profile Reviewed')).toBe(false);
    // Do not leave the shared inventory-outlet option renamed for later browser tests.
    await page.getByTestId('outlet-profile-name').fill('E2E Inventory Outlet');
    await page.getByTestId('outlet-managed-password').fill(password);
    const restored=page.waitForResponse(r=>r.url().includes('/internal/admin/outlet-management/')&&r.url().endsWith('/profile')&&r.request().method()==='PATCH');
    await page.getByTestId('outlet-profile-save').click();
    expect((await restored).status()).toBe(200);
    await page.reload();
    await page.getByTestId('outlet-manage-profile-E42').click();
    await expect(page.getByTestId('outlet-profile-name')).toHaveValue('E2E Inventory Outlet');
});

test('protected Admin creates and archives an unused outlet and reads its history', async ({page}) => {
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
    const createdResponse=await created;
    expect(createdResponse.status()).toBe(201);
    const createdCode=(await createdResponse.json() as {data:{outlet_code:string}}).data.outlet_code;
    await expect(page.getByText('E2E Lifecycle Outlet',{exact:false})).toBeVisible();
    await expect(page.getByRole('status')).toHaveText('Outlet change saved.');
    const row=page.getByText('E2E Lifecycle Outlet',{exact:false}).locator('..').locator('..');
    await page.getByTestId('outlet-owner-password').fill(password);
    const archived=page.waitForResponse(r=>r.url().includes('/internal/admin/outlet-management/')&&r.url().endsWith('/archive')&&r.request().method()==='POST');
    await row.getByRole('button',{name:'Archive eligible outlet'}).click();
    expect((await archived).status()).toBe(200);
    await expect(page.getByText('E2E Lifecycle Outlet',{exact:false})).toContainText('E2E Lifecycle Outlet');
    await expect(page.getByText(/E2E Lifecycle Outlet.*archived/)).toBeVisible();
    // Archived outlet history is a protected read-only view, not an outlet selector.
    await page.getByTestId('outlet-history-'+createdCode).click();
    await expect(page.getByTestId('outlet-archive-summary-'+createdCode)).toContainText('Closed cash sessions: 0; cash entries: 0');
    await expect(row.getByRole('button',{name:'Edit profile'})).toHaveCount(0);

});

test('Full Access reviews and archives reconciled retained business history', async ({page}) => {
    await login(page,'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/outlet-management'))?.status()).toBe(200);
    const row=page.getByText('E2E D03 Reviewed History Outlet',{exact:false}).locator('..').locator('..');
    await expect(row.getByRole('button',{name:'Archive eligible outlet'})).toBeVisible();
    await page.getByTestId('outlet-owner-password').fill(password);
    const denied=page.waitForResponse(r=>r.url().endsWith('/archive')&&r.request().method()==='POST');
    await row.getByRole('button',{name:'Archive eligible outlet'}).click();
    expect((await denied).status()).toBe(409);
    await expect(row.getByRole('button',{name:'Archive eligible outlet'})).toBeVisible();
    await page.getByTestId('outlet-history-reviewed').check();
    await page.getByTestId('outlet-history-review-note').fill('Reviewed synthetic retained history before archive.');
    const archived=page.waitForResponse(r=>r.url().endsWith('/archive')&&r.request().method()==='POST');
    await row.getByRole('button',{name:'Archive eligible outlet'}).click();
    expect((await archived).status()).toBe(200);
    await page.getByTestId('outlet-history-E44').click();
    await expect(page.getByTestId('outlet-archived-stock-E44')).toContainText('Products: 1');
    await expect(page.getByTestId('outlet-archived-stock-E44')).toContainText('recorded quantity 0');
    await expect(row.getByRole('button',{name:'Archive eligible outlet'})).toHaveCount(0);
});

test('Full Access archives synthetic closed-cash outlet and views preserved read-only amount', async ({page}) => {
    await login(page,'e2e-protected-owner@example.invalid');
    expect((await page.goto('/internal/admin/outlet-management'))?.status()).toBe(200);
    const row=page.getByText('E2E D03 Closed Cash Outlet',{exact:false}).locator('..').locator('..');
    await expect(row.getByRole('button',{name:'Archive eligible outlet'})).toBeVisible();
    await page.getByTestId('outlet-owner-password').fill(password);
    const archive=page.waitForResponse(r=>r.url().endsWith('/archive')&&r.request().method()==='POST');
    await row.getByRole('button',{name:'Archive eligible outlet'}).click();
    expect((await archive).status()).toBe(200);
    await page.getByTestId('outlet-history-E43').click();
    await expect(page.getByTestId('outlet-archive-summary-E43')).toContainText('Closed cash sessions: 1; cash entries: 0');
    await expect(page.getByTestId('outlet-archive-summary-E43')).toContainText('closed; expected 100.00; actual 100.00');
    await expect(row.getByRole('button',{name:'Edit profile'})).toHaveCount(0);
    await expect(row.getByRole('button',{name:'Archive eligible outlet'})).toHaveCount(0);
    await expect(page.getByTestId('outlet-archive-summary-E43')).toContainText('Historical records are read-only');
});

test('operator cannot access archived outlet management or history',async ({page})=> {
    await login(page,'e2e-sales@example.invalid');
    await expect(page.getByTestId('manage-outlets-link')).toHaveCount(0);
    expect((await page.goto('/internal/admin/outlet-management'))?.status()).toBe(403);
    expect((await page.goto('/internal/admin/outlet-management/data'))?.status()).toBe(403);

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

test('personal Admin photo upload and removal stay inside the authenticated account UI', async ({page,browser}) => {
    await login(page,'e2e-mt43@example.invalid');
    await page.getByTestId('my-account-link').click();
    await page.waitForURL('**/internal/admin/manage-account');
    await expect(page.getByTestId('account-photo-workspace')).toBeVisible();
    await expect(page.getByTestId('account-photo-image')).toHaveCount(0);
    await page.getByTestId('account-photo-file').setInputFiles('public/icon-192.png');
    const created=page.waitForResponse(r=>r.url().endsWith('/internal/admin/manage-account/photo')&&r.request().method()==='POST');
    await page.getByTestId('account-photo-upload').click();
    expect((await created).status()).toBe(200);
    await expect(page.getByTestId('account-photo-image')).toBeVisible();
    await expect(page.getByTestId('account-photo-image')).toHaveJSProperty('naturalWidth',192);
    await page.reload();
    await expect(page.getByTestId('account-photo-image')).toHaveJSProperty('naturalWidth',192);
    const other=await browser.newContext();
    const guest=await other.newPage();
    try {
        expect((await guest.goto('/internal/admin/manage-account/photo'))?.status()).toBe(401);
        await login(guest,'e2e-inventory@example.invalid');
        expect((await guest.goto('/internal/admin/manage-account/photo'))?.status()).toBe(404);
    } finally { await releaseSyntheticAdminSession(guest); await other.close(); }
    const removed=page.waitForResponse(r=>r.url().endsWith('/internal/admin/manage-account/photo')&&r.request().method()==='DELETE');
    await page.getByTestId('account-photo-remove').click();
    expect((await removed).status()).toBe(200);
    await expect(page.getByTestId('account-photo-image')).toHaveCount(0);
    expect((await page.request.get('/internal/admin/manage-account/photo')).status()).toBe(404);
});

test('protected POS audit viewer filters true outlet events without leaking payloads or operator access', async ({page,browser}) => {
    expect((await page.goto('/internal/admin/pos/audit'))?.status()).toBe(401);
    await login(page,'e2e-audit-owner@example.invalid');
    await expect(page.getByTestId('audit-viewer-link')).toBeVisible();
    await page.getByTestId('audit-viewer-link').click();
    await page.waitForURL('**/internal/admin/pos/audit');
    await expect(page.getByRole('heading',{name:'POS audit'})).toBeVisible();
    await expect(page.getByTestId('audit-row')).toHaveCount(2);
    await expect(page.getByText('MT75 E2E North Audit')).toBeVisible();
    await expect(page.getByText('MT75 E2E South Audit')).toBeVisible();
    await expect(page.locator('body')).not.toContainText('[REDACTED]');
    await page.getByTestId('audit-search').fill('South');
    await page.getByTestId('audit-filter-submit').click();
    await expect(page.getByTestId('audit-row')).toHaveCount(1);
    await expect(page.getByText('MT75 E2E South Audit')).toBeVisible();
    await expect(page.getByText('MT75 E2E North Audit')).toHaveCount(0);
    const northValue=await page.getByTestId('audit-outlet').locator('option').filter({hasText:/^E41 ·/}).getAttribute('value');
    expect(northValue).toMatch(/^[0-9a-f-]{36}$/);
    await page.getByTestId('audit-outlet').selectOption(northValue!);
    await page.getByTestId('audit-filter-submit').click();
    await expect(page.getByTestId('audit-empty')).toBeVisible();
    await page.goto('/internal/admin/pos');
    await page.getByTestId('logout').click();
    await page.waitForURL('**/internal/admin/pos/login');
    const context=await browser.newContext();
    try {
        const restricted=await context.newPage();
        await login(restricted,'e2e-digital-operations@example.invalid');
        await expect(restricted.getByTestId('audit-viewer-link')).toHaveCount(0);
        expect((await restricted.goto('/internal/admin/pos/audit'))?.status()).toBe(403);
        await restricted.goto('/internal/admin/pos');
        await restricted.getByTestId('logout').click();
        await restricted.waitForURL('**/internal/admin/pos/login');
    } finally {await context.close();}
});


test('protected owner saves and reloads the original nine POS portal preferences', async ({page}) => {
    expect((await page.goto('/internal/admin/pos/portal-preferences'))?.status()).toBe(401);
    await login(page,'e2e-pref-owner@example.invalid');
    await expect(page.getByTestId('portal-preferences-link')).toBeVisible();
    await page.getByTestId('portal-preferences-link').click();
    await page.waitForURL('**/internal/admin/pos/portal-preferences');
    await expect(page.getByRole('heading',{name:'POS portal preferences'})).toBeVisible();
    await expect(page.getByTestId('pref-invoice_page_length')).toHaveValue('15');
    await expect(page.getByTestId('pref-inventory_page_length')).toHaveValue('10');
    await expect(page.getByTestId('pref-auto_focus_search')).toBeChecked();
    await page.getByTestId('pref-invoice_page_length').selectOption('50');
    await page.getByTestId('pref-inventory_page_length').selectOption('100');
    await page.getByTestId('pref-invoice_search_category').selectOption('customer_name');
    await page.getByTestId('pref-auto_focus_search').uncheck();
    await page.getByTestId('pref-remember_search').check();
    const saved=page.waitForResponse(r=>r.url().endsWith('/internal/admin/pos/portal-preferences')&&r.request().method()==='PUT');
    await page.getByTestId('portal-preferences-save').click();
    expect((await saved).status()).toBe(200);
    await expect(page.getByRole('status')).toHaveText('Portal preferences saved.');
    await page.reload();
    await expect(page.getByTestId('pref-invoice_page_length')).toHaveValue('50');
    await expect(page.getByTestId('pref-inventory_page_length')).toHaveValue('100');
    await expect(page.getByTestId('pref-invoice_search_category')).toHaveValue('customer_name');
    await expect(page.getByTestId('pref-auto_focus_search')).not.toBeChecked();
    await expect(page.getByTestId('pref-remember_search')).toBeChecked();
});
