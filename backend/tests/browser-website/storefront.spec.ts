import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function state(input: { mode?: 'hybrid' | 'digital_only' | 'commerce_only'; action?: 'acquire-alpha' }) {
    execFileSync('php', [
        'artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder',
        '--env=testing', '--force',
    ], {
        cwd: process.cwd(),
        stdio: 'inherit',
        env: {
            ...process.env,
            WEBSITE_E2E_MODE: input.mode ?? '',
            WEBSITE_E2E_ACTION: input.action ?? '',
        },
    });
}

async function noHorizontalOverflow(page: import('@playwright/test').Page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
}

test('MT-5.1 storefront keeps catalogue private fresh mode-aware SEO-safe and responsive', async ({ page, request }) => {
    test.setTimeout(120_000);
    const browserRequests: string[] = [];
    page.on('request', req => browserRequests.push(req.url()));

    await page.goto('/');
    await expect(page.getByRole('heading', { name: /Mobile products and digital solutions/i })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Products' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Discuss a digital project' })).toBeVisible();
    await expect(page.getByText('MT51 Alpha Phone')).toBeVisible();

    await page.goto('/products');
    await expect(page.getByRole('heading', { name: 'Products', exact: true })).toBeVisible();
    await expect(page.locator('article')).toHaveCount(12);
    await expect(page.getByText('MT51 Zero Stock')).toBeVisible();
    const zeroCard = page.getByText('MT51 Zero Stock').locator('xpath=ancestor::article[1]');
    await expect(zeroCard).toContainText('Out of stock');
    await expect(page.getByRole('link', { name: 'Next page →' })).toBeVisible();

    await page.goto('/products?q=Alpha');
    await expect(page.locator('article')).toHaveCount(1);
    await expect(page.getByText('MT51 Alpha Phone')).toBeVisible();

    await page.goto('/products?category=mobile_phone');
    await expect(page.locator('article')).toHaveCount(3);
    await expect(page.getByText('MT51 Alpha Phone')).toBeVisible();
    await expect(page.getByText('MT51 Zero Stock')).toBeVisible();

    await page.goto('/products');
    await page.getByRole('link', { name: 'Next page →' }).click();
    await expect(page.getByText('MT51 Accessory 10')).toBeVisible();
    await expect(page.getByText('MT51 Accessory 11')).toBeVisible();

    await page.goto('/products/mt51-alpha-phone');
    await expect(page.getByRole('heading', { name: 'MT51 Alpha Phone' })).toBeVisible();
    await expect(page.getByText('1 available')).toBeVisible();
    await expect(page.getByText('Standard')).toBeVisible();
    await expect(page).toHaveTitle(/MT51 Alpha Phone \| mobiST Technologies/);
    const canonical = await page.locator('link[rel="canonical"]').getAttribute('href');
    expect(canonical).toBe('https://mobisttech.com/products/mt51-alpha-phone');
    const jsonLd = await page.locator('script[type="application/ld+json"]').textContent();
    expect(jsonLd).toContain('"@type":"Product"');
    expect(jsonLd).toContain('https://schema.org/InStock');
    const rendered = (await page.content()).toLowerCase();
    for (const privateField of ['purchase_price', 'unit_no', 'imei', 'object_key']) {
        expect(rendered).not.toContain(privateField);
    }

    state({ action: 'acquire-alpha' });
    await page.reload();
    await expect(page.getByText('2 available')).toBeVisible();

    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.ok()).toBe(true);
    const sitemapText = await sitemap.text();
    expect(sitemapText).toContain('https://mobisttech.com/products/mt51-alpha-phone');
    const robots = await request.get('/robots.txt');
    expect(robots.ok()).toBe(true);
    const robotsText = await robots.text();
    expect(robotsText).toContain('Disallow: /cart');
    expect(robotsText).toContain('Disallow: /checkout');

    await page.setViewportSize({ width: 390, height: 844 });
    for (const path of ['/', '/products', '/products/mt51-alpha-phone', '/categories', '/compare']) {
        await page.goto(path);
        await noHorizontalOverflow(page);
    }

    state({ mode: 'digital_only' });
    browserRequests.length = 0;
    await page.goto('/');
    await expect(page.getByRole('heading', { name: /Digital solutions built around your business/i })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Products' })).toHaveCount(0);
    await expect(page.getByText('Live catalogue')).toHaveCount(0);
    const digitalHtml = (await page.content()).toLowerCase();
    expect(digitalHtml).not.toContain('mt51 alpha phone');
    expect(browserRequests.some(url => url.includes('/api/v1/'))).toBe(false);
    const digitalProducts = await page.goto('/products');
    expect(digitalProducts?.status()).toBe(404);
    await expect(page.getByRole('heading', { name: 'This page is not active.' })).toBeVisible();

    state({ mode: 'commerce_only' });
    browserRequests.length = 0;
    await page.goto('/');
    await expect(page.getByRole('heading', { name: /Mobile technology, clearly available/i })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Products' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Discuss a digital project' })).toHaveCount(0);
    expect(browserRequests.some(url => url.includes('/api/v1/'))).toBe(false);
    await noHorizontalOverflow(page);

    const API = 'http://127.0.0.1:18080/api/v1/catalogue/products/mt51-alpha-phone';
    const apiResponse = await request.get(API);
    expect(apiResponse.ok()).toBe(true);
    const apiJson = JSON.stringify(await apiResponse.json()).toLowerCase();
    for (const privateField of ['purchase_price', 'unit_no', 'imei', 'object_key']) {
        expect(apiJson).not.toContain(privateField);
    }
});
