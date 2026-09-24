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
    execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\W06PresentationE2eCleanupSeeder', '--env=testing', '--force'], {
        cwd: process.cwd(), stdio: 'inherit',
    });
    execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\W06SecondSoftwareE2eCleanupSeeder', '--env=testing', '--force'], {
        cwd: process.cwd(), stdio: 'inherit',
    });
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\DynamicWebsiteE2eCleanupSeeder',
        '--env=testing', '--force',
    ], { cwd: process.cwd(), stdio: 'inherit' });
    execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\W06ManagedSocialMediaE2eCleanupSeeder', '--env=testing', '--force'], {
        cwd: process.cwd(), stdio: 'inherit',
    });
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
    // The 180s limit expired in authenticated logout after 20+ real route assertions had passed.
    // Bound the full second-product acceptance to 300s, including mandatory teardown.
    test.setTimeout(300_000);
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
        // A real protected rollback must restore the first approved revision, including media,
        // sitemap and historical release, without changing the independently published MT54.
        const rolled = admin.waitForResponse(response => response.url().includes('/internal/admin/platform/software/revisions/')
            && response.url().endsWith('/rollback') && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Rollback v1' }).click();
        expect((await rolled).status()).toBe(200);
        expect((await page.goto(newUrl))?.status()).toBe(200);
        await expect(page).toHaveTitle(/Second Software Unique SEO Heading/);
        await expect(page.getByText('Current version: 1.0.0')).toBeVisible();
        expect((await page.request.get(newUrl + '/media/' + assetId)).status()).toBe(200);
        expect((await (await page.request.get('/sitemap.xml')).text())).toContain(newUrl + '/releases');
        expect((await page.goto(newUrl + '/releases/1.0.0'))?.status()).toBe(200);
        await page.setViewportSize({ width: 390, height: 844 });
        expect((await page.goto(newUrl))?.status()).toBe(200);
        await expect(page.getByRole('heading', { name: 'MT75 W06 Second Software' })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2)).toBe(true);
        // Canonical slug is a real authenticated separate workflow, preserving exact old public links.
        const renamedUrl = '/software/mt75-w06-second-renamed';
        await release.getByPlaceholder('new-canonical-slug').fill('mt75-w06-second-renamed');
        await release.getByPlaceholder('Approved reason').fill('Synthetic approved public canonical rename.');
        const changedSlug = admin.waitForResponse(response => response.url().endsWith(`/internal/admin/platform/software/${createdId}/slug`)
            && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Change canonical slug' }).click();
        expect((await changedSlug).status()).toBe(200);
        expect((await page.goto(renamedUrl))?.status()).toBe(200);
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/software\/mt75-w06-second-renamed$/);
        const oldRoute = await page.request.get(newUrl, { maxRedirects: 0 });
        expect(oldRoute.status()).toBe(308);
        expect(oldRoute.headers()['location']).toContain(renamedUrl);
        const oldRelease = await page.request.get(newUrl + '/releases/1.0.0', { maxRedirects: 0 });
        expect(oldRelease.status()).toBe(308);
        expect(oldRelease.headers()['location']).toContain(renamedUrl + '/releases/1.0.0');
        expect((await page.goto(renamedUrl + '/releases/1.0.0'))?.status()).toBe(200);
        const renamedMap = await (await page.request.get('/sitemap.xml')).text();
        expect(renamedMap).toContain(renamedUrl + '/releases');
        expect(renamedMap).not.toContain(newUrl + '/releases');
        // Archival removes ONLY this product's public family and scoped media, not MT54 history.
        const archived = admin.waitForResponse(response => response.url().endsWith(`/internal/admin/platform/software/${createdId}/archive`)
            && response.request().method() === 'POST');
        await release.getByRole('button', { name: 'Archive' }).click();
        expect((await archived).status()).toBe(200);
        for (const suffix of ['', '/privacy', '/terms', '/faq', '/releases', '/releases/1.0.0']) {
            expect((await page.goto(renamedUrl + suffix))?.status(), `archived ${suffix}`).toBe(404);
        }
        expect((await page.request.get(renamedUrl + '/media/' + assetId)).status()).toBe(404);
        expect((await page.request.get(newUrl, { maxRedirects: 0 })).status()).toBe(404);
        expect((await page.request.get(newUrl + '/releases/1.0.0', { maxRedirects: 0 })).status()).toBe(404);
        expect((await (await page.request.get('/sitemap.xml')).text())).not.toContain(renamedUrl);
        expect((await page.goto(firstUrl))?.status()).toBe(200);
        await expect(page.getByText('Published MT54 software overview')).toBeVisible();
        expect((await page.goto(firstUrl + '/releases/1.0.0'))?.status()).toBe(200);
    } finally {
        await releasePlatformBrowserAdmin(admin);
        await admin.close();
    }
});

test('W06 actual Admin managed-page SEO revision remains private until public publish', async ({ page, context }) => {
    test.setTimeout(150_000);
    const url = '/mt54-case-study';
    expect((await page.goto(url))?.status()).toBe(200);
    await expect(page.getByText('Published anonymous case study.')).toBeVisible();
    const originalSitemap = await page.request.get('/sitemap.xml');
    expect(await originalSitemap.text()).toContain(url);
    const admin = await context.newPage();
    try {
        await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
        await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
        await admin.getByTestId('login-password').fill('SyntheticPass123!');
        await admin.getByTestId('login-submit').click();
        await admin.waitForURL('**/internal/admin/pos');
        await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
        await expect(admin.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();
        await admin.getByRole('button', { name: 'Content', exact: true }).click();
        const mediaSaved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/media') && r.request().method() === 'POST');
        await admin.getByRole('heading', { name: 'Website media library' }).locator('xpath=ancestor::section[1]')
            .locator('input[type="file"]').setInputFiles({
                name: 'mt75-w06-managed-social.png', mimeType: 'image/png',
                buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=', 'base64'),
            });
        const savedMedia = await mediaSaved;
        expect(savedMedia.status()).toBe(200);
        const assetId: number = (await savedMedia.json()).data.id;
        expect(assetId).toBeGreaterThan(0);
        const mediaUrl = url + '/media/' + assetId;
        expect((await page.request.get(mediaUrl)).status()).toBe(404);
        expect((await page.request.get('/software/mt54-software/media/' + assetId)).status()).toBe(404);
        const editor = admin.getByRole('heading', { name: 'Managed pages & digital content' }).locator('xpath=ancestor::section[1]');
        const caseOption = editor.locator('select').first().locator('option', { hasText: 'MT54 Case Study' });
        const caseId = await caseOption.getAttribute('value');
        expect(caseId).toBeTruthy();
        await editor.locator('select').first().selectOption(caseId!);
        await editor.getByRole('combobox', { name: 'Managed page template' }).selectOption('wide');
        await expect(editor.getByPlaceholder('Managed page SEO title')).toHaveValue('');
        await editor.getByPlaceholder('Managed page SEO title').fill('W06 Independent Managed SEO Title');
        await editor.getByPlaceholder('Managed page SEO description').fill('W06 private draft SEO description.');
        await editor.getByPlaceholder('Managed page canonical URL').fill('/mt54-case-study');
        await editor.getByPlaceholder('Managed page social title').fill('W06 Managed Social Heading');
        await editor.getByPlaceholder('Managed page social description').fill('W06 managed social description.');
        await editor.getByLabel('Managed page social image').selectOption(String(assetId));
        await editor.getByLabel('Include managed page in sitemap').uncheck();
        const draft = admin.waitForResponse(response => response.url().endsWith('/internal/admin/platform/pages/draft')
            && response.request().method() === 'POST');
        await editor.getByRole('button', { name: 'Save page draft' }).click();
        expect((await draft).status()).toBe(200);
        await expect(editor.getByRole('button', { name: 'Publish v3' })).toBeVisible();
        await editor.getByText('Preview private draft v3').click();
        const draftPreview = editor.getByLabel('Private managed page preview v3');
        await expect(draftPreview).toContainText('wide template');
        await expect(draftPreview).toContainText('Private case-study draft.');
        await page.goto(url);
        await expect(page.getByText('Published anonymous case study.')).toBeVisible();
        await expect(page).not.toHaveTitle(/W06 Independent Managed SEO Title/);
        expect((await page.request.get(mediaUrl)).status()).toBe(404);
        expect(await (await page.request.get('/sitemap.xml')).text()).toContain(url);
        const published = admin.waitForResponse(response => /\/internal\/admin\/platform\/pages\/[0-9]+\/publish$/.test(response.url())
            && response.request().method() === 'POST');
        await editor.getByRole('button', { name: 'Publish v3' }).click();
        expect((await published).status()).toBe(200);
        expect((await page.goto(url))?.status()).toBe(200);
        await expect(page).toHaveTitle(/W06 Independent Managed SEO Title/);
        await expect(page.locator('[data-page-template="wide"]')).toBeVisible();
        await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', 'W06 private draft SEO description.');
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /\/mt54-case-study$/);
        await expect(page.locator('meta[property="og:title"]')).toHaveAttribute('content', 'W06 Managed Social Heading');
        await expect(page.locator('meta[property="og:image"]')).toHaveAttribute('content', new RegExp(`/mt54-case-study/media/${assetId}$`));
        await expect(page.locator('meta[name="twitter:image"]')).toHaveAttribute('content', new RegExp(`/mt54-case-study/media/${assetId}$`));
        const publicImage = await page.request.get(mediaUrl);
        expect(publicImage.status()).toBe(200);
        expect(publicImage.headers()['content-type']).toContain('image/png');
        expect((await publicImage.body()).length).toBe(68);
        expect((await page.request.get('/software/mt54-software/media/' + assetId)).status()).toBe(404);
        await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', /noindex/);
        expect((await (await page.request.get('/sitemap.xml')).text())).not.toContain(url);
        await page.goto('/');
        await expect(page.getByRole('heading', { name: 'MT54 managed homepage' })).toBeVisible();
    } finally {
        await releasePlatformBrowserAdmin(admin);
        await admin.close();
    }
});

test('W06 genuine Admin presentation joins nested navigation and scoped banners through private draft publish mode and rollback', async ({ page, context }) => {
    test.setTimeout(240_000);
    await page.goto('/');
    await expect(page.getByText('W06 Browser First announcement')).toHaveCount(0);
    await expect(page.getByText('W06 Solutions Hub', { exact: true })).toHaveCount(0);
    const admin = await context.newPage();
    try {
        await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
        await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
        await admin.getByTestId('login-password').fill('SyntheticPass123!');
        await admin.getByTestId('login-submit').click();
        await admin.waitForURL('**/internal/admin/pos');
        await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
        await expect(admin.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();
        const presentation = admin.getByRole('heading', { name: 'Website presentation, branding, theme, navigation & SEO' })
            .locator('xpath=ancestor::section[1]');
        const draftField = presentation.locator('textarea');
        await expect(presentation.getByRole('button', { name: 'Rollback', exact: true })).toHaveCount(0);
        const first = {
            navigation: [
                { key: 'mt75-w06-parent', label: 'W06 Solutions Hub', destination_type: 'route', destination_key: 'services', capability_scope: 'digital', sort_order: 10 },
                { key: 'mt75-w06-child', parent_key: 'mt75-w06-parent', label: 'W06 Request Project', destination_type: 'route', destination_key: 'enquiry', capability_scope: 'digital', sort_order: 11 },
                { key: 'mt75-w06-commerce', label: 'W06 Shop Hub', destination_type: 'route', destination_key: 'products', capability_scope: 'commerce', sort_order: 20 },
            ],
            promotion: {
                announcement: { text: 'W06 Browser First announcement', href: '/services', scope: 'digital' },
                banners: [
                    { title: 'W06 Digital Spotlight', body: 'Synthetic service launch', href: '/services', scope: 'digital' },
                    { title: 'W06 Commerce Spotlight', body: 'Synthetic product launch', href: '/products', scope: 'commerce' },
                ],
            },
        };
        await draftField.fill(JSON.stringify(first));
        const firstSaved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/presentation/draft') && r.request().method() === 'POST');
        await presentation.getByRole('button', { name: 'Save presentation draft' }).click();
        const firstResponse = await firstSaved;
        expect(firstResponse.status()).toBe(200);
        const firstId: number = (await firstResponse.json()).data.id;
        expect(firstId).toBeGreaterThan(0);
        await page.goto('/');
        await expect(page.getByText('W06 Browser First announcement')).toHaveCount(0);
        await expect(page.getByText('W06 Solutions Hub', { exact: true })).toHaveCount(0);
        await expect(presentation.getByRole('button', { name: 'Publish', exact: true })).toHaveCount(1);
        const publishedFirst = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${firstId}/publish`) && r.request().method() === 'POST');
        await presentation.getByRole('button', { name: 'Publish', exact: true }).click();
        expect((await publishedFirst).status()).toBe(200);
        await page.goto('/');
        const bannerSection = page.getByRole('region', { name: 'Published Website banners' });
        await expect(page.getByRole('status', { name: 'Site announcement' })).toContainText('W06 Browser First announcement');
        await expect(bannerSection.getByRole('heading', { name: 'W06 Digital Spotlight' })).toBeVisible();
        await expect(bannerSection.getByRole('heading', { name: 'W06 Commerce Spotlight' })).toBeVisible();
        const nav = page.getByRole('navigation', { name: 'Primary' });
        await expect(nav.getByText('W06 Solutions Hub', { exact: true })).toBeVisible();
        await expect(nav.getByRole('link', { name: 'W06 Request Project' })).toBeHidden();
        await nav.getByText('W06 Solutions Hub', { exact: true }).click();
        await expect(nav.getByRole('link', { name: 'W06 Request Project' })).toBeVisible();
        await expect(nav.getByRole('link', { name: 'W06 Request Project' })).toHaveAttribute('href', '/enquiry');
        await expect(nav.getByRole('link', { name: 'W06 Shop Hub' })).toHaveAttribute('href', '/products');

        // A real published mode switch removes digital discovery while retaining commerce content.
        state('commerce_only');
        await page.goto('/');
        await expect(page.getByRole('status', { name: 'Site announcement' })).toHaveCount(0);
        await expect(page.getByText('W06 Solutions Hub', { exact: true })).toHaveCount(0);
        await expect(page.getByText('W06 Request Project')).toHaveCount(0);
        await expect(page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'W06 Shop Hub' })).toBeVisible();
        await expect(page.getByRole('region', { name: 'Published Website banners' }).getByRole('heading', { name: 'W06 Commerce Spotlight' })).toBeVisible();
        await expect(page.getByText('W06 Digital Spotlight')).toHaveCount(0);
        state('hybrid');
        await page.goto('/');
        await expect(page.getByText('W06 Browser First announcement')).toBeVisible();

        // A newer private revision must not affect the published navigation or promotion.
        const second = {
            navigation: first.navigation.map(row => row.key === 'mt75-w06-parent' ? { ...row, is_visible: false } : row),
            promotion: {
                announcement: { text: 'W06 Browser Updated announcement', href: '/products', scope: 'commerce' },
                banners: [{ title: 'W06 Updated Commerce Spotlight', body: 'Changed only after publication', href: '/products', scope: 'commerce' }],
            },
        };
        await draftField.fill(JSON.stringify(second));
        const secondSaved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/presentation/draft') && r.request().method() === 'POST');
        await presentation.getByRole('button', { name: 'Save presentation draft' }).click();
        const secondResponse = await secondSaved;
        expect(secondResponse.status()).toBe(200);
        const secondId: number = (await secondResponse.json()).data.id;
        await page.goto('/');
        await expect(page.getByText('W06 Browser First announcement')).toBeVisible();
        await expect(page.getByText('W06 Browser Updated announcement')).toHaveCount(0);
        await expect(page.getByText('W06 Solutions Hub', { exact: true })).toBeVisible();
        await expect(presentation.getByRole('button', { name: 'Publish', exact: true })).toHaveCount(1);
        const publishedSecond = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${secondId}/publish`) && r.request().method() === 'POST');
        await presentation.getByRole('button', { name: 'Publish', exact: true }).click();
        expect((await publishedSecond).status()).toBe(200);
        await page.goto('/');
        await expect(page.getByText('W06 Browser Updated announcement')).toBeVisible();
        await expect(page.getByText('W06 Solutions Hub', { exact: true })).toHaveCount(0);
        await expect(page.getByText('W06 Request Project')).toHaveCount(0);
        await expect(page.getByRole('heading', { name: 'W06 Updated Commerce Spotlight' })).toBeVisible();
        await expect(page.getByText('W06 Digital Spotlight')).toHaveCount(0);
        // The superseded v1 is selected intentionally: rollback creates an auditable new publication.
        const rollbacks = presentation.getByRole('button', { name: 'Rollback', exact: true });
        await expect(rollbacks).toHaveCount(2);
        const restored = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${firstId}/rollback`) && r.request().method() === 'POST');
        await rollbacks.last().click();
        expect((await restored).status()).toBe(200);
        await page.goto('/');
        await expect(page.getByText('W06 Browser First announcement')).toBeVisible();
        await expect(page.getByText('W06 Solutions Hub', { exact: true })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'W06 Digital Spotlight' })).toBeVisible();
        await expect(page.getByText('W06 Browser Updated announcement')).toHaveCount(0);
        // Guided builder: item editor/reorder, section visibility/order, header/footer and private preview.
        const guided = admin.getByRole('region', { name: 'Guided Website presentation builder' });
        await expect(guided).toBeVisible();
        await guided.getByRole('button', { name: 'Add navigation item' }).click();
        const added = guided.getByTestId('guided-nav-3');
        await added.getByRole('textbox', { name: 'Navigation key 3' }).fill('mt75-w06-guided');
        await added.getByRole('textbox', { name: 'Navigation label 3' }).fill('W06 Guided News');
        await added.getByRole('textbox', { name: 'Navigation destination 3' }).fill('services');
        await added.getByRole('button', { name: 'Up' }).click();
        await expect(guided.locator('aside[aria-label="Navigation draft preview"]')).toContainText('W06 Guided News');
        await guided.getByRole('checkbox', { name: 'Show products' }).uncheck();
        await guided.getByRole('spinbutton', { name: 'Order contact' }).fill('1');
        await guided.getByRole('textbox', { name: 'Footer description' }).fill('W06 Guided Footer');
        await guided.getByRole('textbox', { name: 'Footer copyright' }).fill('W06 synthetic copyright');
        await guided.getByRole('checkbox', { name: 'show account' }).uncheck();
        await guided.getByRole('checkbox', { name: 'sticky' }).check();
        await guided.getByRole('checkbox', { name: 'show search' }).check();
        await guided.getByRole('checkbox', { name: 'show cart' }).uncheck();
        await guided.getByRole('checkbox', { name: 'footer show logo' }).check();
        await guided.getByRole('checkbox', { name: 'footer show navigation' }).check();
        await guided.getByRole('combobox', { name: 'Footer navigation layout' }).selectOption('two_columns');
        await guided.getByRole('combobox', { name: 'Contact CTA', exact: true }).selectOption('contact');
        await guided.getByRole('textbox', { name: 'Contact CTA label' }).fill('W06 Email Us');
        await expect(guided.locator('aside[aria-label="Homepage draft preview"]')).not.toContainText('products');
        const guidedSaved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/presentation/draft') && r.request().method() === 'POST');
        await guided.getByRole('button', { name: 'Save guided presentation draft' }).click();
        const guidedResponse = await guidedSaved;
        expect(guidedResponse.status()).toBe(200);
        const guidedId: number = (await guidedResponse.json()).data.id;
        await page.goto('/');
        await expect(page.getByText('W06 Guided Footer')).toHaveCount(0);
        await expect(page.getByRole('heading', { name: 'Available products' })).toBeVisible();
        const guidedPublished = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${guidedId}/publish`) && r.request().method() === 'POST');
        await presentation.getByRole('button', { name: 'Publish', exact: true }).click();
        expect((await guidedPublished).status()).toBe(200);
        await page.goto('/');
        await expect(page.getByText('W06 Guided Footer')).toBeVisible();
        await expect(page.getByText('W06 synthetic copyright')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Available products' })).toHaveCount(0);
        await expect(page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Account' })).toHaveCount(0);
        await expect(page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'W06 Guided News' })).toHaveAttribute('href', '/services');
        await expect(page.getByRole('region', { name: 'Contact mobiST' })).toBeVisible();
        await expect(page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Search products' })).toBeVisible();
        await expect(page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Cart' })).toHaveCount(0);
        await expect(page.getByRole('navigation', { name: 'Footer navigation' })).toHaveClass(/sm:grid-cols-2/);
        await expect(page.getByRole('link', { name: 'W06 Email Us' })).toHaveAttribute('href', /^mailto:/);
        await expect(page.locator('header')).toHaveClass(/sticky/);
    } finally {
        // Do not permit a synthetic Website mode change to spill into neighboring browser tests.
        try { state('hybrid'); } finally {
            await releasePlatformBrowserAdmin(admin);
            await admin.close();
        }
    }
});


test('W06 published global SEO remains draft-isolated and separate from product-specific metadata', async ({ page, context }) => {
    test.setTimeout(150_000);
    await page.goto('/');
    await expect(page).not.toHaveTitle(/W06 Global SEO /);
    const admin = await context.newPage();
    try {
        await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
        await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
        await admin.getByTestId('login-password').fill('SyntheticPass123!');
        await admin.getByTestId('login-submit').click();
        await admin.waitForURL('**/internal/admin/pos');
        await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
        const editor = admin.getByRole('heading', { name: 'Global Website SEO' }).locator('xpath=ancestor::section[1]');
        await editor.getByPlaceholder('Global SEO title').fill('W06 Global SEO Published Homepage');
        await editor.getByPlaceholder('Global SEO description').fill('W06 global description, revision one.');
        await editor.getByPlaceholder('Global social title').fill('W06 Global SEO Social Preview');
        await editor.getByPlaceholder('Global social description').fill('W06 global social description.');
        await editor.getByLabel('Set homepage canonical to published Website root').check();
        const saved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/presentation/draft') && r.request().method() === 'POST');
        await editor.getByRole('button', { name: 'Save global SEO draft' }).click();
        const result = await saved;
        expect(result.status()).toBe(200);
        const id: number = (await result.json()).data.id;
        await page.goto('/');
        await expect(page).not.toHaveTitle(/W06 Global SEO /);
        await expect(page.locator('meta[name="description"]')).not.toHaveAttribute('content', 'W06 global description, revision one.');
        const presentation = admin.getByRole('heading', { name: 'Website presentation, branding, theme, navigation & SEO' }).locator('xpath=ancestor::section[1]');
        const published = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${id}/publish`) && r.request().method() === 'POST');
        await presentation.getByRole('button', { name: 'Publish', exact: true }).click();
        expect((await published).status()).toBe(200);
        await page.goto('/');
        await expect(page).toHaveTitle(/W06 Global SEO Published Homepage/);
        await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', 'W06 global description, revision one.');
        await expect(page.locator('meta[property="og:title"]')).toHaveAttribute('content', 'W06 Global SEO Social Preview');
        await expect(page.locator('link[rel="canonical"]')).toHaveAttribute('href', /https:\/\/mobisttech\.com\/?$/);
        await page.goto('/software/mt54-software');
        await expect(page).toHaveTitle(/MT54 Software/);
        await expect(page).not.toHaveTitle(/W06 Global SEO Published Homepage/);
    } finally {
        await releasePlatformBrowserAdmin(admin);
        await admin.close();
    }
});
