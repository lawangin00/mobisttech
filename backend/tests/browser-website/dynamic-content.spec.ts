import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

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
