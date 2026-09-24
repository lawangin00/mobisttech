import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

// Admin lives on Laravel :18080. Closing a page does not release its protected desktop slot.
async function releasePlatformBrowserAdmin(admin: import('@playwright/test').Page) {
    if (admin.isClosed()) return;
    const home = await admin.goto('http://127.0.0.1:18080/internal/admin/pos');
    if (home?.status() !== 200) return;
    const logout = admin.getByTestId('logout');
    if (await logout.count() !== 1) return;
    const completed = admin.waitForResponse(response => response.url().endsWith('/internal/admin/auth/logout')
        && response.request().method() === 'POST');
    await logout.click();
    expect((await completed).status()).toBe(200);
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

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => {
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\DynamicWebsiteE2eSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
});

test.afterAll(() => {
    execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\W06SecondSoftwareE2eCleanupSeeder', '--env=testing', '--force'], {
        cwd: process.cwd(), stdio: 'inherit',
    });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\DynamicWebsiteE2eCleanupSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
});

test('MT-5.4 published CMS, digital enquiry and Software Product routes render without draft leakage', async ({ page, request }) => {
    test.setTimeout(120_000);

    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'MT54 managed homepage' })).toBeVisible();
    await expect(page.getByText('Managed homepage published content.')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Privacy Policy' })).toBeVisible();

    await page.getByRole('link', { name: 'Privacy Policy' }).click();
    await expect(page.getByRole('heading', { name: 'Privacy Policy' })).toBeVisible();
    await expect(page.getByText('Version 1 · Effective 2026-09-18')).toBeVisible();
    await expect(page.getByText('Published MT54 privacy facts.')).toBeVisible();

    await page.goto('/services');
    await expect(page.getByRole('heading', { name: 'Digital Services' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'MT54 Web Development' })).toBeVisible();
    await Promise.all([
        page.waitForURL('**/services/mt54-web-development'),
        page.getByRole('link', { name: 'MT54 Web Development' }).click(),
    ]);
    await expect(page.getByRole('heading', { name: 'MT54 Web Development' })).toBeVisible();
    await expect(page.getByText('Starter')).toBeVisible();
    await expect(page.getByText('PKR 10000.00')).toBeVisible();

    await page.goto('/enquiry?service=mt54-web-development');
    await page.getByLabel('Name').fill('MT54 Browser Lead');
    await page.getByLabel('Mobile').fill('03005554444');
    await page.getByLabel('Package').selectOption({ label: 'Starter · PKR 10000.00' });
    await page.getByLabel('What do you need?').fill('Need a deterministic public Website project.');
    const submitted = page.waitForResponse((response) =>
        response.url().endsWith('/api/public/enquiries')
        && response.request().method() === 'POST'
        && response.status() === 201);
    await page.getByRole('button', { name: 'Send enquiry' }).click();
    await submitted;
    await expect(page.getByRole('status')).toContainText('Enquiry submitted');

    await page.goto('/mt54-case-study');
    await expect(page.getByRole('heading', { name: 'MT54 Case Study' })).toBeVisible();
    await expect(page.getByText('Published anonymous case study.')).toBeVisible();
    await expect(page.getByText('Private case-study draft.')).toHaveCount(0);

    await page.goto('/software/mt54-software');
    await expect(page.getByRole('heading', { name: 'MT54 Software' })).toBeVisible();
    await expect(page.getByText('Published MT54 software overview')).toBeVisible();
    await expect(page.getByText('Private MT54 software draft')).toHaveCount(0);
    await expect(page.getByText('Current version: 1.0.0')).toBeVisible();

    await page.goto('/software/mt54-software/privacy');
    await expect(page.getByRole('heading', { name: 'MT54 Software Privacy' })).toBeVisible();
    await expect(page.getByText('MT54 product privacy.')).toBeVisible();

    await page.goto('/software/mt54-software/terms');
    await expect(page.getByRole('heading', { name: 'MT54 Software Terms' })).toBeVisible();
    await expect(page.getByText('MT54 product terms.')).toBeVisible();

    await page.goto('/software/mt54-software/faq');
    await expect(page.getByRole('heading', { name: 'MT54 Software FAQ' })).toBeVisible();
    await expect(page.getByText('Is this published?')).toBeVisible();

    await page.goto('/software/mt54-software/releases');
    await expect(page.getByRole('heading', { name: 'MT54 Software Releases' })).toBeVisible();
    await page.getByRole('link', { name: '1.0.0' }).click();
    await expect(page.getByRole('heading', { name: '1.0.0' })).toBeVisible();
    await expect(page.getByText('Reusable public Software Product pages')).toBeVisible();

    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.status()).toBe(200);
    const xml = await sitemap.text();
    expect(xml).toContain('/services/mt54-web-development');
    expect(xml).toContain('/mt54-case-study');
    expect(xml).toContain('/privacy-policy');
    expect(xml).toContain('/software/mt54-software/releases');
});

test('MT-5.4 commerce-only mode prunes digital discovery while common legal and Software history remain', async ({ page }) => {
    test.setTimeout(60_000);
    state('commerce_only');
    try {
        expect((await page.goto('/services'))?.status()).toBe(404);
        expect((await page.goto('/enquiry'))?.status()).toBe(404);
        expect((await page.goto('/mt54-case-study'))?.status()).toBe(404);

        await page.goto('/privacy-policy');
        await expect(page.getByRole('heading', { name: 'Privacy Policy' })).toBeVisible();

        await page.goto('/software/mt54-software');
        await expect(page.getByRole('heading', { name: 'MT54 Software' })).toBeVisible();
        await expect(page.getByText('Current version: 1.0.0')).toBeVisible();
    } finally {
        state('hybrid');
    }
});


test('MT-7.5 authenticated Software draft publication reaches actual Next.js public page', async ({ page, context }) => {
    test.setTimeout(120_000);
    const url = '/software/mt54-software';
    await page.goto(url);
    await expect(page.getByText('Published MT54 software overview')).toBeVisible();
    await expect(page.getByText('Private MT54 software draft')).toHaveCount(0);
    const admin = await context.newPage();
    try {
        await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
        await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
        await admin.getByTestId('login-password').fill('SyntheticPass123!');
        await admin.getByTestId('login-submit').click();
        await admin.waitForURL('**/internal/admin/pos');
        await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
        await expect(admin.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();
        await admin.getByRole('button', { name: 'Software', exact: true }).click();
        const editor = admin.getByRole('heading', { name: 'Software create/edit' }).locator('xpath=ancestor::section[1]');
        await editor.locator('select').first().selectOption({ label: 'MT54 Software · published' });
        await editor.getByText('Preview selected revision routes').click();
        const preview = editor.getByRole('region', { name: 'Protected software route preview' });
        await expect(preview.getByText('Private MT54 software draft')).toBeVisible();
        const release = admin.getByRole('heading', { name: 'Publish, rollback, release & canonical slug' }).locator('xpath=ancestor::section[1]');
        await release.getByRole('button', { name: 'Publish draft v2' }).click();
        await expect(release.getByRole('button', { name: 'Rollback v2' })).toBeVisible();
        await page.goto(url);
        await expect(page.getByText('Private MT54 software draft')).toBeVisible();
        await expect(page.getByText('Published MT54 software overview')).toHaveCount(0);
        await editor.getByPlaceholder('Overview HTML').fill('<p>MT75 subsequent private revision</p>');
        const draftSaved = admin.waitForResponse(response => response.url().includes('/internal/admin/platform/software/') && response.url().endsWith('/draft') && response.request().method() === 'POST');
        await editor.getByRole('button', { name: 'Save new draft revision' }).click();
        expect((await draftSaved).status()).toBe(200);
        await expect(release.getByRole('button', { name: 'Publish draft v3' })).toBeVisible({ timeout: 20000 });
        await page.goto(url);
        await expect(page.getByText('Private MT54 software draft')).toBeVisible();
        await expect(page.getByText('MT75 subsequent private revision')).toHaveCount(0);
        const published = admin.waitForResponse(response => response.url().includes('/internal/admin/platform/software/revisions/') && response.url().endsWith('/publish') && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Publish draft v3' }).click();
        expect((await published).status()).toBe(200);
        await expect(release.getByRole('button', { name: 'Rollback v3' })).toBeVisible({ timeout: 20000 });
        await page.goto(url);
        await expect(page.getByText('MT75 subsequent private revision')).toBeVisible();
        const rolledBack = admin.waitForResponse(response => response.url().includes('/internal/admin/platform/software/revisions/') && response.url().endsWith('/rollback') && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Rollback v1' }).click();
        expect((await rolledBack).status()).toBe(200);
        await expect(release.getByRole('button', { name: 'Rollback v4' })).toBeVisible({ timeout: 20000 });
        await page.goto(url);
        await expect(page.getByText('Published MT54 software overview')).toBeVisible({ timeout: 15000 });
        await expect(page.getByText('MT75 subsequent private revision')).toHaveCount(0);
        await expect(page.getByText('Current version: 1.0.0')).toBeVisible();
    } finally {
        await releasePlatformBrowserAdmin(admin);
        await admin.close();
    }
});

test('W06 new independent Software is private until published with its own complete route family', async ({ page, context }) => {
    test.setTimeout(180_000);
    const newUrl = '/software/mt75-w06-second';
    const firstUrl = '/software/mt54-software';
    expect((await page.goto(newUrl))?.status()).toBe(404);
    await page.goto(firstUrl);
    await expect(page.getByText('Published MT54 software overview')).toBeVisible();
    const admin = await context.newPage();
    try {
        await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
        await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
        await admin.getByTestId('login-password').fill('SyntheticPass123!');
        await admin.getByTestId('login-submit').click();
        await admin.waitForURL('**/internal/admin/pos');
        await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
        await expect(admin.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();
        // An actual protected Admin upload must remain private until a published Software snapshot references it.
        await admin.getByRole('button', { name: 'Content', exact: true }).click();
        const mediaResponse = admin.waitForResponse(response => response.url().endsWith('/internal/admin/platform/media')
            && response.request().method() === 'POST');
        await admin.getByRole('heading', { name: 'Website media library' }).locator('xpath=ancestor::section[1]')
            .locator('input[type="file"]').setInputFiles({
                name: 'mt75-w06-media.png', mimeType: 'image/png',
                buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEklEQVR4nGMQmhUlNCuKAUIBABuWBBkXZEqoAAAAAElFTkSuQmCC', 'base64'),
            });
        const uploaded = await mediaResponse;
        expect(uploaded.status()).toBe(200);
        const uploadedBody = await uploaded.json();
        const assetId: number = uploadedBody.data.id;
        expect(assetId).toBeGreaterThan(0);
        await admin.getByRole('button', { name: 'Software', exact: true }).click();
        const editor = admin.getByRole('heading', { name: 'Software create/edit' }).locator('xpath=ancestor::section[1]');
        await editor.locator('select').first().selectOption('');
        await expect(editor.getByPlaceholder('Product name')).toHaveValue('');
        await editor.getByPlaceholder('Product name').fill('MT75 W06 Second Software');
        await editor.getByPlaceholder('product-slug').fill('mt75-w06-second');
        await editor.getByPlaceholder('Summary').fill('Second synthetic Software has independent approved content.');
        await editor.getByPlaceholder('Overview HTML').fill('<p>Second product only: private W06 overview.</p>');
        await editor.getByPlaceholder('Product Privacy HTML').fill('<p>Second software privacy only.</p>');
        await editor.getByPlaceholder('Product Terms HTML').fill('<p>Second software terms only.</p>');
        await editor.getByPlaceholder('FAQ JSON').fill('[{"question":"Second software?","answer":"<p>Independent FAQ answer.</p>"}]');
        await editor.getByPlaceholder('Software SEO title').fill('Second Software Unique SEO Heading');
        await editor.getByPlaceholder('Software SEO description').fill('Second Software only SEO description.');
        await editor.getByPlaceholder('Software canonical URL (optional)').fill('/software/mt75-w06-second');
        await editor.getByPlaceholder('Software social title').fill('Second Software Social Heading');
        await editor.getByPlaceholder('Software social description').fill('Second Software social description.');
        await editor.getByPlaceholder('Hero media ID').fill(String(assetId));
        await editor.getByPlaceholder('Logo media ID').fill(String(assetId));
        await editor.getByPlaceholder('Screenshot IDs').fill(String(assetId));
        await editor.getByLabel('Social image asset').selectOption(String(assetId));
        const created = admin.waitForResponse(response => response.url().endsWith('/internal/admin/platform/software')
            && response.request().method() === 'POST');
        await editor.getByRole('button', { name: 'New Software draft' }).click();
        expect((await created).status()).toBe(200);
        const candidate = editor.locator('select').first().locator('option', { hasText: 'MT75 W06 Second Software' });
        await expect(candidate).toHaveCount(1);
        const createdId = await candidate.getAttribute('value');
        expect(createdId).toBeTruthy();
        await editor.locator('select').first().selectOption(createdId!);
        await editor.getByText('Preview selected revision routes').click();
        const preview = editor.getByRole('region', { name: 'Protected software route preview' });
        await expect(preview.getByText('Second product only: private W06 overview.')).toBeVisible();
        await preview.getByRole('button', { name: 'Privacy' }).click();
        await expect(preview.getByText('Second software privacy only.')).toBeVisible();
        await preview.getByRole('button', { name: 'Terms' }).click();
        await expect(preview.getByText('Second software terms only.')).toBeVisible();
        await preview.getByRole('button', { name: 'FAQ' }).click();
        await expect(preview.getByText('Independent FAQ answer.')).toBeVisible();
        await preview.getByRole('button', { name: 'Releases' }).click();
        await expect(preview.getByText('No release records yet.')).toBeVisible();
        expect((await page.goto(newUrl))?.status()).toBe(404);
        expect((await page.request.get(newUrl + '/media/' + assetId)).status()).toBe(404);
        expect((await page.request.get(firstUrl + '/media/' + assetId)).status()).toBe(404);
        await page.goto(firstUrl);
        await expect(page.getByText('Published MT54 software overview')).toBeVisible();
        await expect(page.getByText('Second product only: private W06 overview.')).toHaveCount(0);
        // Publish the SECOND product through actual permission-protected Admin UI.
        const release = admin.getByRole('heading', { name: 'Publish, rollback, release & canonical slug' }).locator('xpath=ancestor::section[1]');
        const published = admin.waitForResponse(response => response.url().includes('/internal/admin/platform/software/revisions/')
            && response.url().endsWith('/publish') && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Publish draft v1' }).click();
        expect((await published).status()).toBe(200);
        await expect(release.getByRole('button', { name: 'Rollback v1' })).toBeVisible();
        expect((await page.goto(newUrl))?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'MT75 W06 Second Software' })).toBeVisible();
        await expect(page.getByText('Second product only: private W06 overview.')).toBeVisible();
        // Published SEO override intentionally replaces the name-based browser title.
        await expect(page).toHaveTitle(/Second Software Unique SEO Heading/);
        await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', 'Second Software only SEO description.');
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/software\/mt75-w06-second$/);
        await expect(page.locator('meta[property="og:title"]')).toHaveAttribute('content', 'Second Software Social Heading');
        await expect(page.locator('meta[property="og:description"]')).toHaveAttribute('content', 'Second Software social description.');
        await expect(page.locator('meta[name="twitter:title"]')).toHaveAttribute('content', 'Second Software Social Heading');
        await expect(page.locator('meta[property="og:image"]')).toHaveAttribute('content', new RegExp('/software/mt75-w06-second/media/' + assetId + '$'));
        const mediaUrl = newUrl + '/media/' + assetId;
        const mediaResult = await page.request.get(mediaUrl);
        expect(mediaResult.status()).toBe(200);
        expect(mediaResult.headers()['content-type']).toContain('image/png');
        expect((await mediaResult.body()).length).toBeGreaterThan(60);
        await expect(page.getByRole('img', { name: 'MT75 W06 Second Software hero image' })).toBeVisible();
        await expect(page.getByRole('img', { name: 'MT75 W06 Second Software logo' })).toBeVisible();
        await expect(page.getByRole('img', { name: 'MT75 W06 Second Software screenshot' })).toBeVisible();
        await expect.poll(() => page.getByRole('img', { name: 'MT75 W06 Second Software hero image' })
            .evaluate((image: HTMLImageElement) => image.naturalWidth)).toBe(2);
        expect((await page.request.get(firstUrl + '/media/' + assetId)).status()).toBe(404);
        expect((await page.request.get(newUrl + '/media/999999')).status()).toBe(404);
        for (const [route, title, expected] of [
            ['privacy', 'MT75 W06 Second Software Privacy', 'Second software privacy only.'],
            ['terms', 'MT75 W06 Second Software Terms', 'Second software terms only.'],
            ['faq', 'MT75 W06 Second Software FAQ', 'Independent FAQ answer.'],
        ] as const) {
            expect((await page.goto(newUrl + '/' + route))?.status(), route).toBe(200);
            await expect(page.getByRole('heading', { name: title })).toBeVisible();
            await expect(page.getByText(expected)).toBeVisible();
        }
        await release.getByPlaceholder('1.0.0').fill('1.0.0');
        await release.getByPlaceholder('Customer-readable release summary').fill('Second synthetic product first release only.');
        const draftedRelease = admin.waitForResponse(response => /\/internal\/admin\/platform\/software\/[0-9a-f-]+\/releases$/.test(response.url())
            && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Save release draft' }).click();
        expect((await draftedRelease).status()).toBe(200);
        await expect(release.getByRole('button', { name: 'Publish release' })).toBeVisible();
        const publishedRelease = admin.waitForResponse(response => /\/internal\/admin\/platform\/software\/releases\/[0-9]+\/publish$/.test(response.url())
            && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Publish release' }).click();
        expect((await publishedRelease).status()).toBe(200);
        expect((await page.goto(newUrl + '/releases'))?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'MT75 W06 Second Software Releases' })).toBeVisible();
        await expect(page.getByRole('link', { name: '1.0.0' })).toBeVisible();
        expect((await page.goto(newUrl + '/releases/1.0.0'))?.status()).toBe(200);
        await expect(page.getByText('Second synthetic product first release only.')).toBeVisible();
        expect((await page.goto(newUrl + '/releases/9.9.9'))?.status()).toBe(404);
        const sitemap = await page.request.get('/sitemap.xml');
        expect(sitemap.status()).toBe(200);
        expect(await sitemap.text()).toContain(newUrl + '/releases');
        // The new product's publication and release must not overwrite the first product.
        await page.goto(firstUrl);
        await expect(page.getByText('Published MT54 software overview')).toBeVisible();
        await expect(page.getByText('Current version: 1.0.0')).toBeVisible();
        await expect(page.getByText('Second product only: private W06 overview.')).toHaveCount(0);
        await expect(page.locator('meta[property="og:title"]')).not.toHaveAttribute('content', 'Second Software Social Heading');
        // A subsequent SEO-only draft is private until publication, and sitemap=false must suppress discovery without deleting history.
        await editor.getByPlaceholder('Software SEO title').fill('Second Software Revised SEO Heading');
        await editor.getByLabel('Include Software in sitemap').uncheck();
        const revised = admin.waitForResponse(response => response.url().includes('/internal/admin/platform/software/')
            && response.url().endsWith('/draft') && response.request().method() === 'POST');
        await editor.getByRole('button', { name: 'Save new draft revision' }).click();
        expect((await revised).status()).toBe(200);
        await expect(release.getByRole('button', { name: 'Publish draft v2' })).toBeVisible();
        await page.goto(newUrl);
        await expect(page).toHaveTitle(/Second Software Unique SEO Heading/);
        const before = await page.request.get('/sitemap.xml');
        expect(await before.text()).toContain(newUrl + '/releases');
        const publishedSeo = admin.waitForResponse(response => response.url().includes('/internal/admin/platform/software/revisions/')
            && response.url().endsWith('/publish') && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Publish draft v2' }).click();
        expect((await publishedSeo).status()).toBe(200);
        await expect(release.getByRole('button', { name: 'Rollback v2' })).toBeVisible();
        await page.goto(newUrl);
        await expect(page).toHaveTitle(/Second Software Revised SEO Heading/);
        const after = await page.request.get('/sitemap.xml');
        const xml = await after.text();
        expect(xml).not.toContain(newUrl);
        expect(xml).toContain(firstUrl + '/releases');
        expect((await page.goto(newUrl + '/releases/1.0.0'))?.status()).toBe(200);
    } finally {
        await releasePlatformBrowserAdmin(admin);
        await admin.close();
    }
});