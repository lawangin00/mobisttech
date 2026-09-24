import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

// Admin is on Laravel :18080; the POS helper's relative :13000 Website URL cannot release this session.
async function releaseWebsiteTestAdmin(admin: import('@playwright/test').Page) {
    if (admin.isClosed()) return;
    const home = await admin.goto('http://127.0.0.1:18080/internal/admin/pos');
    if (home?.status() !== 200) return;
    const logout = admin.getByTestId('logout');
    if (await logout.count() !== 1) return;
    const response = admin.waitForResponse(result => result.url().endsWith('/internal/admin/auth/logout')
        && result.request().method() === 'POST');
    await logout.click();
    expect((await response).status()).toBe(200);
    await admin.waitForURL('**/internal/admin/pos/login');
}

function state(mode: 'hybrid' | 'digital_only' | 'commerce_only') {
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder',
        '--env=testing', '--force',
    ], {
        cwd: process.cwd(),
        stdio: 'inherit',
        env: { ...process.env, WEBSITE_E2E_MODE: mode, WEBSITE_E2E_ACTION: '' },
    });
}

async function login(page: import('@playwright/test').Page) {
    await page.goto('/account');
    await page.getByLabel('Email').fill('mt52-customer@example.invalid');
    await page.getByLabel('Password').fill('SyntheticPass123!');
    const loginReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/auth/login')
        && response.request().method() === 'POST');
    const accountReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/account') && response.status() === 200);
    const projectsReady = page.waitForResponse((response) =>
        response.url().endsWith('/api/customer/projects') && response.status() === 200);
    await page.locator('form').getByRole('button', { name: 'Sign in', exact: true }).click();
    const loginResponse = await loginReady;
    expect(loginResponse.status(), 'customer login status').toBe(200);
    await Promise.all([accountReady, projectsReady]);
    await expect(page.getByRole('heading', { name: 'MT52 Customer' })).toBeVisible();
}
test('MT-5.5 customer project portal preserves private history and truthful provider availability', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);

    const projectLink = page.getByRole('link', { name: 'MT55 Client Project', exact: true });
    await expect(projectLink).toBeVisible();
    await expect(projectLink.locator('..')).toContainText('delivered');
    const projectReady = page.waitForResponse((response) =>
        /\/api\/customer\/projects\/[0-9a-f-]+$/.test(response.url()) && response.status() === 200);
    await projectLink.click();
    await projectReady;

    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    await expect(page.getByText('MT55 Client Project Service · delivered')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Proposals & milestones' }).locator('..')).toContainText('MT55 Approved Scope');
    await expect(page.getByText('PKR 10000.00', { exact: true })).toBeVisible();
    await expect(page.getByText('No external payment provider is configured.', { exact: true })).toBeVisible();

    const files = page.getByRole('heading', { name: 'Private files' }).locator('..');
    await expect(files).toContainText('mt55-client-reference.txt');
    await expect(files).toContainText('mt55-delivery.txt');
    const downloadPromise = page.waitForEvent('download');
    await files.getByRole('link', { name: 'Download securely' }).last().click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBe('mt55-delivery.txt');

    const upload = page.waitForResponse((response) =>
        response.url().includes('/api/customer/projects/')
        && response.url().endsWith('/files/reference')
        && response.request().method() === 'POST'
        && response.status() === 201);
    await files.locator('input[type=file]').setInputFiles({
        name: 'mt55-browser-reference.txt',
        mimeType: 'text/plain',
        buffer: Buffer.from('MT55 browser private reference'),
    });
    await files.getByRole('button', { name: 'Upload reference' }).click();
    await upload;
    await expect(page.getByRole('status')).toHaveText('Reference file uploaded.');
    await expect(files).toContainText('mt55-browser-reference.txt');

    const history = page.getByRole('heading', { name: 'Project history' }).locator('..');
    await expect(history).toContainText('proposal approved');
    await expect(history.getByText('project status changed', { exact: true })).toHaveCount(4);

    state('commerce_only');
    await page.reload();
    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    state('digital_only');
    await page.reload();
    await expect(page.getByRole('heading', { name: 'MT55 Client Project' })).toBeVisible();
    state('hybrid');
});

test('W04 project milestone initiation failure retains its owned order for continuation without another order', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);
    const fakeOrderId = '00000000-0000-4000-8000-000000000085';
    const fakePaymentId = '00000000-0000-4000-8000-000000000086';
    await page.route('**/api/customer/project-payment-channels', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: [
            { code: 'jazzcash', label: 'JazzCash', instructions: 'Use hosted wallet checkout.', available: true },
            { code: 'easypaisa', label: 'Easypaisa', available: false },
            { code: 'card', label: 'Credit / Debit Card', available: false },
        ] } }) });
    });
    let creates = 0;
    await page.route('**/api/customer/project-milestones/pay', async (route) => {
        creates += 1;
        await new Promise((resolve) => setTimeout(resolve, 350));
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: {
            order_id: fakeOrderId, payment_id: fakePaymentId,
        } }) });
    });
    let initiations = 0;
    await page.route(`**/api/customer/payments/${fakePaymentId}/initiate`, async (route) => {
        initiations += 1;
        await route.fulfill({ status: 502, contentType: 'application/json', body: JSON.stringify({ message: 'Synthetic milestone gateway unavailable.' }) });
    });
    await page.route(`**/api/customer/orders/${fakeOrderId}`, async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            id: fakeOrderId, number: 'MT75-PROJECT-RECOVERY', type: 'digital',
            status: 'pending', fulfillment_status: 'pending', payment_status: 'unpaid',
            subtotal: '10000.00', total: '10000.00', currency: 'PKR', items: [],
            payments: [{ public_id: fakePaymentId, gateway: 'jazzcash', status: 'pending', amount: '10000.00', currency: 'PKR' }],
            signed_access_url: 'https://example.invalid/synthetic-status',
        } }) });
    });
    await page.getByRole('link', { name: 'MT55 Client Project', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Pay with JazzCash' })).toBeEnabled();
    await expect(page.getByText('Use hosted wallet checkout.', { exact: true })).toBeVisible();
    await page.evaluate(() => {
        const pay = [...document.querySelectorAll('button')].find((button) => button.textContent === 'Pay with JazzCash');
        if (!pay) throw new Error('Synthetic milestone payment option missing.');
        pay.click(); pay.click();
    });
    await expect(page.getByRole('status')).toHaveText('Synthetic milestone gateway unavailable.');
    await expect(page.getByRole('link', { name: 'View order and continue payment' }))
        .toHaveAttribute('href', `/account/orders/${fakeOrderId}`);
    await expect(page.getByRole('button', { name: 'Pay with JazzCash' })).toBeDisabled();
    expect(creates).toBe(1);
    expect(initiations).toBe(1);
    await page.getByRole('link', { name: 'View order and continue payment' }).click();
    await expect(page).toHaveURL(`http://127.0.0.1:13000/account/orders/${fakeOrderId}`);
    await expect(page.getByRole('heading', { name: 'MT75-PROJECT-RECOVERY' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cancel order' })).toHaveCount(0);
    expect(creates).toBe(1);
});


test('W04 failed digital milestone order does not offer commerce-only retry', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);
    const orderId = '00000000-0000-4000-8000-000000000087';
    await page.route('**/api/customer/checkout/channels', async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { items: [
            { code: 'cod', label: 'Cash on Delivery', available: true },
            { code: 'jazzcash', label: 'JazzCash', available: true },
            { code: 'easypaisa', label: 'Easypaisa', available: false },
            { code: 'card', label: 'Credit / Debit Card', available: false },
        ] } }) });
    });
    await page.route('**/api/customer/orders/' + orderId, async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            id: orderId, number: 'MT75-FAILED-PROJECT', type: 'digital', status: 'pending',
            fulfillment_status: 'pending', payment_status: 'failed', subtotal: '10000.00', total: '10000.00',
            currency: 'PKR', items: [], signed_access_url: '/account', payments: [
                { public_id: '00000000-0000-4000-8000-000000000088', gateway: 'jazzcash', status: 'failed', amount: '10000.00', currency: 'PKR' },
            ],
        } }) });
    });
    await page.goto('/account/orders/' + orderId);
    await expect(page.getByRole('heading', { name: 'MT75-FAILED-PROJECT' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Retry with JazzCash' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Continue payment' })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Cancel order' })).toHaveCount(0);
});

test('W04 owned pending digital order reports missing hosted continuation without creating another payment', async ({ page }) => {
    test.setTimeout(90_000);
    await login(page);
    const orderId = '00000000-0000-4000-8000-000000000091';
    const paymentId = '00000000-0000-4000-8000-000000000092';
    let initiations = 0;
    await page.route('**/api/customer/orders/' + orderId, async (route) => {
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {
            id: orderId, number: 'MT75-PROJECT-NO-REDIRECT', type: 'digital', status: 'pending',
            fulfillment_status: 'pending', payment_status: 'unpaid', subtotal: '10000.00', total: '10000.00',
            currency: 'PKR', items: [], signed_access_url: '/account', payments: [
                { public_id: paymentId, gateway: 'jazzcash', status: 'pending', amount: '10000.00', currency: 'PKR' },
            ],
        } }) });
    });
    await page.route(`**/api/customer/payments/${paymentId}/initiate`, async (route) => {
        initiations += 1;
        await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { payment_id: paymentId } }) });
    });
    await page.goto('/account/orders/' + orderId);
    await expect(page.getByRole('heading', { name: 'MT75-PROJECT-NO-REDIRECT' })).toBeVisible();
    await page.getByRole('button', { name: 'Continue payment' }).click();
    await expect(page.getByText('Payment provider did not return a continuation URL. Your order is saved; try continuing from this page.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Continue payment' })).toBeEnabled();
    await expect(page.getByRole('button', { name: 'Retry with JazzCash' })).toHaveCount(0);
    expect(initiations).toBe(1);
});
test('W05 real Admin delivery becomes privately available to its Customer without hosted payment', async ({ page, browser }) => {
    test.setTimeout(120_000);
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    try {
        await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
        await admin.getByTestId('login-email').fill('e2e-digital-operations@example.invalid');
        await admin.getByTestId('login-password').fill('SyntheticPass123!');
        await admin.getByTestId('login-submit').click();
        await admin.waitForURL('**/internal/admin/pos');
        await admin.goto('http://127.0.0.1:18080/internal/admin/digital-operations');
        await expect(admin.getByRole('heading', { name: 'Digital Operations' })).toBeVisible();
        await admin.getByRole('button', { name: 'Projects', exact: true }).click();
        const projects = admin.getByRole('heading', { name: 'Client projects' }).locator('xpath=ancestor::section[1]');
        await projects.getByRole('button', { name: /MT55/ }).click();
        await expect(admin.getByRole('heading', { name: /MT55-E2E|MT55 Client Project/ })).toBeVisible();
        // The real protected API must reject project completion with an unpaid milestone.
        const blockedCompletion = admin.waitForResponse(response =>
            /\/internal\/admin\/digital-operations\/projects\/[0-9a-f-]+$/.test(response.url())
            && response.request().method() === 'PATCH');
        await admin.locator('select').filter({ has: admin.locator('option[value="completed"]') }).first().selectOption('completed');
        await admin.getByRole('button', { name: 'Transition project' }).click();
        expect((await blockedCompletion).status()).toBe(409);
        await expect(admin.getByRole('alert')).toHaveText('Request failed.');
        const uploadResponse = admin.waitForResponse(response =>
            /\/internal\/admin\/digital-operations\/projects\/[0-9a-f-]+\/files$/.test(response.url())
            && response.request().method() === 'POST');
        await admin.locator('input[type="file"]').setInputFiles({
            name: 'mt55-admin-to-customer-acceptance.txt',
            mimeType: 'text/plain',
            buffer: Buffer.from('W05 owner-scoped private delivery'),
        });
        expect((await uploadResponse).status()).toBe(200); // Admin delivery controller returns JSON 200.
        await login(page);
        await page.getByRole('link', { name: 'MT55 Client Project', exact: true }).click();
        const files = page.getByRole('heading', { name: 'Private files' }).locator('xpath=ancestor::section[1]');
        await expect(files).toContainText('mt55-admin-to-customer-acceptance.txt');
        const downloadPromise = page.waitForEvent('download');
        await files.getByRole('link', { name: 'Download securely' }).last().click();
        expect((await downloadPromise).suggestedFilename()).toBe('mt55-admin-to-customer-acceptance.txt');
    } finally {
        await releaseWebsiteTestAdmin(admin);
        await adminContext.close();
    }
});
test('W05 public enquiry creates an approved owner-bound proposal through real Admin and Customer APIs', async ({ page, browser }) => {
    test.setTimeout(150_000);
    // Independent sixth synthetic account login shares a disposable test-IP throttle bucket.
    // Reset testing-only cache before this journey, never production/session policy.
    execFileSync('php', ['artisan', 'cache:clear', '--env=testing'], { cwd: process.cwd(), stdio: 'inherit' });
    await page.goto('/enquiry?service=mt55-client-project');
    await expect(page.getByRole('heading', { name: 'Project enquiry' })).toBeVisible();
    await page.getByLabel('Name', { exact: true }).fill('MT52 Customer');
    await page.getByLabel('Mobile', { exact: true }).fill('03005200001');
    await page.getByLabel('Email (optional)').fill('mt52-customer@example.invalid');
    await page.getByLabel('What do you need?').fill('W05 joined project acceptance');
    const createdLead = page.waitForResponse(response => response.url().endsWith('/api/public/enquiries')
        && response.request().method() === 'POST');
    await page.getByRole('button', { name: 'Send enquiry' }).click();
    const leadResponse = await createdLead;
    expect(leadResponse.ok()).toBeTruthy();
    const reference = (await leadResponse.json() as { data: { reference: string } }).data.reference;
    expect(reference).toMatch(/^[A-Z0-9-]+$/);
    const adminContext = await browser.newContext();
    const admin = await adminContext.newPage();
    try {
        await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
        await admin.getByTestId('login-email').fill('e2e-digital-operations@example.invalid');
        await admin.getByTestId('login-password').fill('SyntheticPass123!');
        await admin.getByTestId('login-submit').click();
        await admin.waitForURL('**/internal/admin/pos');
        await admin.goto('http://127.0.0.1:18080/internal/admin/digital-operations');
        await expect(admin.getByRole('heading', { name: 'Digital Operations' })).toBeVisible();
        await admin.getByRole('button', { name: 'Leads', exact: true }).click();
        const pipeline = admin.getByRole('heading', { name: 'Enquiry / lead pipeline' }).locator('xpath=ancestor::section[1]');
        await pipeline.getByRole('button', { name: new RegExp(reference) }).click();
        await expect(admin.getByText('W05 joined project acceptance')).toBeVisible();
        await admin.getByPlaceholder('Project title').fill('W05 Joined Browser Project');
        await admin.locator('select').filter({ has: admin.locator('option', { hasText: 'MT52 Customer' }) }).selectOption({ index: 1 });
        const createdProject = admin.waitForResponse(response => /\/internal\/admin\/digital-operations\/leads\/[0-9a-f-]+\/projects$/.test(response.url())
            && response.request().method() === 'POST');
        await admin.getByRole('button', { name: 'Create client project' }).click();
        expect((await createdProject).ok()).toBeTruthy();
        await admin.getByRole('button', { name: 'Projects', exact: true }).click();
        const projects = admin.getByRole('heading', { name: 'Client projects' }).locator('xpath=ancestor::section[1]');
        await projects.getByRole('button', { name: /W05 Joined Browser Project/ }).click();
        await expect(admin.getByRole('heading', { name: /W05 Joined Browser Project/ })).toBeVisible();
        await admin.getByPlaceholder('Proposal title').fill('W05 Exact Proposal');
        await admin.getByPlaceholder('Proposal scope').fill('W05 exact signed-off scope');
        await admin.getByPlaceholder('Exact PKR total e.g. 50000.00').fill('10000.00');
        await admin.locator('input[type="datetime-local"]').fill(new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 16));
        const drafted = admin.waitForResponse(response => /\/internal\/admin\/digital-operations\/projects\/[0-9a-f-]+\/proposals$/.test(response.url())
            && response.request().method() === 'POST');
        await admin.getByRole('button', { name: 'Save proposal revision' }).click();
        expect((await drafted).ok()).toBeTruthy();
        const approved = admin.waitForResponse(response => /\/internal\/admin\/digital-operations\/proposals\/[0-9a-f-]+\/approve$/.test(response.url())
            && response.request().method() === 'POST');
        await admin.getByRole('button', { name: 'Approve proposal' }).click();
        expect((await approved).ok()).toBeTruthy();
        await login(page);
        await page.getByRole('link', { name: 'W05 Joined Browser Project', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'W05 Joined Browser Project' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Proposals & milestones' }).locator('..')).toContainText('W05 exact signed-off scope');
        await expect(page.getByText('PKR 10000.00', { exact: true })).toBeVisible();
        await expect(page.getByText('No external payment provider is configured.', { exact: true })).toBeVisible();
        state('commerce_only');
        await page.reload();
        await expect(page.getByRole('heading', { name: 'W05 Joined Browser Project' })).toBeVisible();
    } finally {
        state('hybrid');
        await releaseWebsiteTestAdmin(admin);
        await adminContext.close();
    }
});
