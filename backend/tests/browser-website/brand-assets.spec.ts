import { expect, test } from '@playwright/test';

test('MT-6.1 production Website serves canonical logo favicon and touch/PWA icons', async ({ page, request }) => {
    for (const [path, contentType] of [
        ['/favicon.ico', 'image'],
        ['/apple-touch-icon.png', 'image/png'],
        ['/icon-192.png', 'image/png'],
        ['/icon-512.png', 'image/png'],
        ['/brand/mobist-wordmark.svg', 'image/svg+xml'],
    ] as const) {
        const response = await request.get(path);
        expect(response.ok(), path).toBe(true);
        expect(response.headers()['content-type'], path).toContain(contentType);
        expect((await response.body()).byteLength, path).toBeGreaterThan(1000);
    }

    await page.goto('/');
    const logo = page.getByRole('img', { name: 'mobiST Technologies' }).first();
    await expect(logo).toBeVisible();
    expect(await logo.evaluate((image: HTMLImageElement) => image.complete && image.naturalWidth > 0)).toBe(true);

    await expect(page.locator('link[rel="icon"][href="/favicon.ico"]')).toHaveCount(1);
    await expect(page.locator('link[rel="apple-touch-icon"][href="/apple-touch-icon.png"]')).toHaveCount(1);
});
