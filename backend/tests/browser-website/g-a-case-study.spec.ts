import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function seed(name: string, action = '') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + name, '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, MT75_GA_CASE_E2E: '1', MT75_GA_CASE_ACTION: action },
  });
}
function mode(value: 'hybrid' | 'digital_only' | 'commerce_only') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, WEBSITE_E2E_MODE: value, WEBSITE_E2E_ACTION: '' },
  });
}

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { seed('DynamicWebsiteE2eSeeder'); seed('GACaseStudyBrowserSeeder', 'seed'); });
test.afterAll(() => {
  try { seed('GACaseStudyBrowserSeeder', 'cleanup'); }
  finally { seed('DynamicWebsiteE2eCleanupSeeder'); }
});

test('G-A case study media visibility order and linked feedback render publicly and remain mode scoped', async ({ page }) => {
  test.setTimeout(150_000);
  await page.setViewportSize({ width: 390, height: 844 });
  try {
    expect((await page.goto('/services/mt54-web-development'))?.status()).toBe(200);
    const related = page.getByRole('heading', { name: 'Related work and client feedback' }).locator('xpath=ancestor::section[1]');
    await expect(related.getByRole('link', { name: 'GA hidden case' })).toHaveCount(0);
    const labels = await related.getByRole('link').allTextContents();
    expect(labels.indexOf('GA earlier case')).toBeGreaterThanOrEqual(0);
    expect(labels.indexOf('GA visible case')).toBeGreaterThan(labels.indexOf('GA earlier case'));

    expect((await page.goto('/ga-e2e-case-hidden'))?.status()).toBe(404);
    expect((await page.goto('/ga-e2e-case-visible'))?.status()).toBe(200);
    await expect(page.getByText('GA visible case body.')).toBeVisible();
    await expect(page.getByTestId('case-disclosure')).toContainText('Anonymous case study');
    await expect(page.getByText('GA_PRIVATE_INDUSTRY')).toHaveCount(0);
    await expect(page.getByText('GA measured case outcome')).toBeVisible();
    const screenshot = page.getByRole('img', { name: 'GA visible case screenshot 1' });
    await expect(screenshot).toBeVisible();
    const screenshotSrc = await screenshot.getAttribute('src');
    expect(screenshotSrc).toMatch(/\/ga-e2e-case-visible\/media\/[1-9][0-9]*$/);
    expect((await page.request.get(screenshotSrc!)).status()).toBe(200);
    await expect(page.getByRole('link', { name: 'GA case-linked feedback' })).toHaveAttribute('href', '/ga-e2e-case-feedback');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);

    mode('commerce_only');
    expect((await page.goto('/ga-e2e-case-visible'))?.status()).toBe(404);
    mode('digital_only');
    expect((await page.goto('/ga-e2e-case-visible'))?.status()).toBe(200);
    await expect(page.getByRole('img', { name: 'GA visible case screenshot 1' })).toBeVisible();
  } finally {
    mode('hybrid');
  }
});
