import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function seed(name: string, action = '') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + name, '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env,
      MT75_GA_ATTRIBUTION_E2E: '1', MT75_GA_ATTRIBUTION_ACTION: action },
  });
}

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => seed('DynamicWebsiteE2eSeeder'));
test.afterAll(() => {
  try { seed('GADigitalAttributionBrowserSeeder', 'cleanup'); }
  finally { seed('DynamicWebsiteE2eCleanupSeeder'); }
});

test('G-A actual service CTA persists privacy-safe page attribution on the created enquiry', async ({ page }) => {
  test.setTimeout(150_000);
  await page.setViewportSize({ width: 390, height: 844 });
  expect((await page.goto('/services/mt54-web-development'))?.status()).toBe(200);
  await page.getByRole('link', { name: 'Discuss this service' }).click();
  await expect(page).toHaveURL(/\/enquiry\?service=mt54-web-development&source=service_page&campaign=service_mt54-web-development$/);
  await expect(page.getByRole('heading', { name: 'Project enquiry' })).toBeVisible();

  await page.getByLabel('Name').fill('GA Attribution Browser');
  await page.getByLabel('Mobile').fill('03001112223');
  await page.getByLabel('What do you need?').fill('Synthetic CTA attribution browser proof.');
  const packageSelect = page.getByLabel('Package');
  if (await packageSelect.count()) {
    const values = await packageSelect.locator('option').evaluateAll(options =>
      options.map(option => (option as HTMLOptionElement).value).filter(Boolean));
    if (values.length > 0) await packageSelect.selectOption(values[0]);
  }

  const submitted = page.waitForResponse(response =>
    response.url().endsWith('/api/public/enquiries') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Send enquiry' }).click();
  expect((await submitted).status()).toBe(201);
  await expect(page.getByRole('status')).toContainText('Enquiry submitted');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);

  seed('GADigitalAttributionBrowserSeeder', 'verify');
});
