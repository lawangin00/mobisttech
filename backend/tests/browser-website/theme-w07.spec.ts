import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function mode(value: 'hybrid' | 'digital_only' | 'commerce_only') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, WEBSITE_E2E_MODE: value, WEBSITE_E2E_ACTION: '' },
  });
}
async function releaseAdmin(admin: import('@playwright/test').Page) {
  if (admin.isClosed()) return;
  const home = await admin.goto('http://127.0.0.1:18080/internal/admin/pos');
  if (home?.status() !== 200) return;
  const logout = admin.getByTestId('logout');
  if (await logout.count() !== 1) return;
  const response = admin.waitForResponse(r => r.url().endsWith('/internal/admin/auth/logout') && r.request().method() === 'POST');
  await logout.click();
  expect((await response).status()).toBe(200);
  await admin.waitForURL('**/internal/admin/pos/login');
}
test.describe.configure({ mode: 'serial' });
test.beforeAll(() => {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\DynamicWebsiteE2eSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit',
  });
});
test.afterAll(() => {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\W07ThemeE2eCleanupSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit',
  });
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\DynamicWebsiteE2eCleanupSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit',
  });
});

test('W07 actual protected theme draft publication rollback and three-mode responsive public styles', async ({ page, context }) => {
  test.setTimeout(240_000);
  await page.goto('/');
  await expect(page.locator('html')).not.toHaveAttribute('style', /--mobist-color-brand:/);
  const admin = await context.newPage();
  try {
    await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
    await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
    await admin.getByTestId('login-password').fill('SyntheticPass123!');
    await admin.getByTestId('login-submit').click();
    await admin.waitForURL('**/internal/admin/pos');
    await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
    await admin.getByRole('button', { name: 'Website', exact: true }).click();
    const theme = admin.getByRole('region', { name: 'Website theme editor' });
    await expect(theme).toBeVisible();
    await theme.getByRole('textbox', { name: 'Website theme primary hex' }).fill('#005b60');
    await theme.getByRole('textbox', { name: 'Website theme background hex' }).fill('#ffffff');
    await expect(theme.locator('aside[aria-label="Private theme preview"]', { hasText: 'Brand primary' })).toBeVisible();
    const draftSaved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/presentation/draft') && r.request().method() === 'POST');
    await theme.getByRole('button', { name: 'Save private theme draft' }).click();
    const draft = await draftSaved;
    expect(draft.status()).toBe(200);
    const firstId: number = (await draft.json()).data.id;
    await page.goto('/');
    await expect(page.locator('html')).not.toHaveAttribute('style', /--mobist-color-brand:/);
    const revisions = admin.getByRole('heading', { name: 'Website presentation, branding, theme, navigation & SEO' }).locator('xpath=ancestor::section[1]');
    const firstPublish = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${firstId}/publish`) && r.request().method() === 'POST');
    await revisions.getByRole('button', { name: 'Publish', exact: true }).click();
    expect((await firstPublish).status()).toBe(200);
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('style', /--mobist-color-brand:\s*#005b60/);
    await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(255, 255, 255)');
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2)).toBe(true);
    for (const value of ['digital_only', 'commerce_only', 'hybrid'] as const) {
      mode(value);
      await page.goto('/');
      await expect(page.locator('html')).toHaveAttribute('style', /--mobist-color-brand:\s*#005b60/);
    }
    await theme.getByRole('textbox', { name: 'Website theme primary hex' }).fill('#008080');
    const secondSaved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/presentation/draft') && r.request().method() === 'POST');
    await theme.getByRole('button', { name: 'Save private theme draft' }).click();
    const second = await secondSaved;
    expect(second.status()).toBe(200);
    const secondId: number = (await second.json()).data.id;
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('style', /--mobist-color-brand:\s*#005b60/);
    const secondPublished = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${secondId}/publish`) && r.request().method() === 'POST');
    await revisions.getByRole('button', { name: 'Publish', exact: true }).click();
    expect((await secondPublished).status()).toBe(200);
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('style', /--mobist-color-brand:\s*#008080/);
    const rolled = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${firstId}/rollback`) && r.request().method() === 'POST');
    await revisions.getByRole('button', { name: 'Rollback', exact: true }).last().click();
    expect((await rolled).status()).toBe(200);
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('style', /--mobist-color-brand:\s*#005b60/);
  } finally {
    mode('hybrid');
    await releaseAdmin(admin);
    await admin.close();
  }
});