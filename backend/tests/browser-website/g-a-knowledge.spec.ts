import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function seed(name: string, action = '') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + name, '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env,
      MT75_GA_KNOWLEDGE_E2E: '1', MT75_GA_KNOWLEDGE_ACTION: action },
  });
}
function mode(value: 'hybrid' | 'digital_only' | 'commerce_only') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, WEBSITE_E2E_MODE: value, WEBSITE_E2E_ACTION: '' },
  });
}

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { seed('DynamicWebsiteE2eSeeder'); seed('GAKnowledgeBrowserSeeder', 'seed'); });
test.afterAll(() => {
  try { seed('GAKnowledgeBrowserSeeder', 'cleanup'); }
  finally { seed('DynamicWebsiteE2eCleanupSeeder'); }
});

test('G-A knowledge discovery renders reusable FAQ taxonomy service guide and isolates private drafts by mode', async ({ page }) => {
  test.setTimeout(150_000);
  await page.setViewportSize({ width: 390, height: 844 });
  try {
    expect((await page.goto('/knowledge'))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Knowledge', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'GA reusable knowledge FAQ' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'GA engineering insight' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'GA service guide' })).toBeVisible();
    await expect(page.getByText('Website Support')).toBeVisible();
    await expect(page.getByText('Laravel')).toBeVisible();
    await expect(page.getByText('Security')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);

    await page.getByRole('link', { name: 'GA reusable knowledge FAQ' }).click();
    await expect(page).toHaveTitle(/GA reusable FAQ SEO/);
    await expect(page.getByTestId('knowledge-taxonomy')).toContainText('Website Support');
    await expect(page.getByTestId('reusable-faq').getByText('How is this FAQ reused?')).toBeVisible();
    await page.getByText('How is this FAQ reused?').click();
    await expect(page.getByText('Through typed published FAQ items.')).toBeVisible();
    await expect(page.getByText('GA_PRIVATE_KNOWLEDGE_DRAFT')).toHaveCount(0);
    await expect(page.getByText('Private future question?')).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'mt54 web development' })).toHaveAttribute('href', '/services/mt54-web-development');

    expect((await page.goto('/ga-e2e-knowledge-guide'))?.status()).toBe(200);
    await expect(page.getByText('GA published service guide.')).toBeVisible();
    await expect(page.getByTestId('knowledge-taxonomy')).toContainText('Getting Started');
    await expect(page.getByRole('link', { name: 'mt54 web development' })).toHaveAttribute('href', '/services/mt54-web-development');

    mode('commerce_only');
    expect((await page.goto('/knowledge'))?.status()).toBe(404);
    expect((await page.goto('/ga-e2e-knowledge-faq'))?.status()).toBe(404);
    mode('digital_only');
    expect((await page.goto('/knowledge'))?.status()).toBe(200);
    await expect(page.getByRole('link', { name: 'GA reusable knowledge FAQ' })).toBeVisible();
  } finally {
    mode('hybrid');
  }
});
