import { expect, test } from '@playwright/test';

test.afterEach(async ({ page }) => {
    const logout = page.getByTestId('logout');
    if (await logout.count()) {
        await logout.click();
        await page.waitForURL('**/internal/admin/pos/login');
    }
});

test('MT-6.1 backend brand assets render on login and authenticated POS shell', async ({ page, request }) => {
    const favicon = await request.get('/favicon.ico');
    expect(favicon.ok()).toBe(true);
    expect(favicon.headers()['content-type']).toContain('image');

    const icon = await request.get('/icon-512.png');
    expect(icon.ok()).toBe(true);
    expect(icon.headers()['content-type']).toContain('image/png');

    await page.goto('/internal/admin/pos/login');
    const loginLogo = page.locator('img[src="/brand/mobist-wordmark.svg"]');
    await expect(loginLogo).toBeVisible();
    expect(await loginLogo.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);

    const watermark = page.locator('img[src="/brand/mobist-mark-watermark.png"]');
    await expect(watermark).toBeAttached();
    expect(await watermark.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);

    await page.getByTestId('login-email').fill('e2e-sales@example.invalid');
    await page.getByTestId('login-password').fill('SyntheticPass123!');
    await page.getByTestId('login-submit').click();
    await page.waitForURL('**/internal/admin/pos');

    const shellLogo = page.locator('aside img[src="/brand/mobist-wordmark.svg"]');
    await expect(shellLogo).toBeVisible();
    expect(await shellLogo.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);

    await expect(page.locator('link[rel="icon"][href="/favicon.ico"]')).toHaveCount(1);
    await expect(page.locator('link[rel="apple-touch-icon"][href="/apple-touch-icon.png"]')).toHaveCount(1);
});
