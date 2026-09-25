import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function seed(name: string, action = '') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + name, '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env,
      MT75_GA_TESTIMONIAL_E2E: '1', MT75_GA_TESTIMONIAL_ACTION: action },
  });
}
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
  const done = admin.waitForResponse(r => r.url().endsWith('/internal/admin/auth/logout') && r.request().method() === 'POST');
  await logout.click();
  expect((await done).status()).toBe(200);
}

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { seed('DynamicWebsiteE2eSeeder'); seed('GATestimonialBrowserSeeder', 'seed'); });
test.afterAll(() => {
  try { seed('GATestimonialBrowserSeeder', 'cleanup'); }
  finally { seed('DynamicWebsiteE2eCleanupSeeder'); }
});

test('G-A protected testimonial moderation publishes ordered consented feedback, isolates drafts and rolls back', async ({ page, context }) => {
  test.setTimeout(210_000);
  await page.setViewportSize({ width: 390, height: 844 });
  try {
    expect((await page.goto('/services/mt54-web-development'))?.status()).toBe(200);
    await expect(page.getByRole('link', { name: 'GA first approved testimonial' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'GA pending testimonial' })).toHaveCount(0);
    expect((await page.goto('/ga-e2e-testimonial-pending'))?.status()).toBe(404);

    const admin = await context.newPage();
    try {
      await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
      await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
      await admin.getByTestId('login-password').fill('SyntheticPass123!');
      await admin.getByTestId('login-submit').click();
      await admin.waitForURL('**/internal/admin/pos');
      await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
      await admin.getByRole('button', { name: 'Content', exact: true }).click();
      const editor = admin.getByRole('heading', { name: 'Managed pages & digital content' }).locator('xpath=ancestor::section[1]');
      const option = editor.locator('select').first().locator('option', { hasText: 'GA pending testimonial' });
      const pageId = await option.getAttribute('value');
      expect(pageId).toBeTruthy();
      await editor.locator('select').first().selectOption(pageId!);

      const controls = editor.getByRole('group', { name: 'Digital testimonial controls' });
      await expect(controls.getByLabel('Testimonial consent confirmed')).toBeChecked();
      await expect(controls.getByLabel('Testimonial moderation state')).toHaveValue('pending');
      await expect(controls.getByLabel('Testimonial display enabled')).not.toBeChecked();
      const pendingCard = editor.locator('div.rounded.border.p-3').filter({ hasText: 'GA pending testimonial · /ga-e2e-testimonial-pending' }).first();
      await expect(pendingCard.getByRole('button', { name: 'Publish v1' })).toBeDisabled();

      await controls.getByLabel('Testimonial moderation state').selectOption('approved');
      await controls.getByLabel('Testimonial display enabled').check();
      await controls.getByLabel('Testimonial display order').fill('20');
      await controls.getByLabel('Testimonial case study slugs').fill('ga-e2e-testimonial-case');
      const saveApproved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/pages/draft') && r.request().method() === 'POST');
      await editor.getByRole('button', { name: 'Save page draft' }).click();
      const savedApproved = await saveApproved; expect(savedApproved.status()).toBe(200);
      const approvedId: number = (await savedApproved.json()).data.id;
      const publishApproved = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/pages/' + approvedId + '/publish') && r.request().method() === 'POST');
      await editor.getByTestId('managed-page-publish-' + approvedId).click();
      expect((await publishApproved).status()).toBe(200);

      expect((await page.goto('/services/mt54-web-development'))?.status()).toBe(200);
      const related = page.getByRole('heading', { name: 'Related work and client feedback' }).locator('xpath=ancestor::section[1]');
      const feedbackLinks = await related.getByRole('link').allTextContents();
      expect(feedbackLinks.indexOf('GA first approved testimonial')).toBeGreaterThanOrEqual(0);
      expect(feedbackLinks.indexOf('GA pending testimonial')).toBeGreaterThan(feedbackLinks.indexOf('GA first approved testimonial'));
      await page.getByRole('link', { name: 'GA pending testimonial' }).click();
      await expect(page.getByText('GA pending feedback body.')).toBeVisible();
      await expect(page.getByTestId('digital-testimonial-status')).toContainText('Published client feedback');
      await expect(page.getByRole('link', { name: 'ga e2e testimonial case' })).toHaveAttribute('href', '/ga-e2e-testimonial-case');
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);

      await admin.bringToFront();
      await editor.locator('textarea[placeholder="Safe HTML content"]').fill('<p>GA private future testimonial.</p>');
      await controls.getByLabel('Testimonial moderation state').selectOption('pending');
      await controls.getByLabel('Testimonial display enabled').uncheck();
      const savePrivate = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/pages/draft') && r.request().method() === 'POST');
      await editor.getByRole('button', { name: 'Save page draft' }).click();
      const privateResponse = await savePrivate; expect(privateResponse.status()).toBe(200);
      const privateId: number = (await privateResponse.json()).data.id;
      await expect(editor.getByTestId('managed-page-publish-' + privateId)).toBeDisabled();
      await page.bringToFront();
      await page.goto('/ga-e2e-testimonial-pending');
      await expect(page.getByText('GA pending feedback body.')).toBeVisible();
      await expect(page.getByText('GA private future testimonial.')).toHaveCount(0);

      await admin.bringToFront();
      await controls.getByLabel('Testimonial moderation state').selectOption('approved');
      await controls.getByLabel('Testimonial display enabled').check();
      await editor.locator('textarea[placeholder="Safe HTML content"]').fill('<p>GA second public testimonial.</p>');
      const saveSecond = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/pages/draft') && r.request().method() === 'POST');
      await editor.getByRole('button', { name: 'Save page draft' }).click();
      const secondResponse = await saveSecond; expect(secondResponse.status()).toBe(200);
      const secondId: number = (await secondResponse.json()).data.id;
      const publishSecond = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/pages/' + secondId + '/publish') && r.request().method() === 'POST');
      await editor.getByTestId('managed-page-publish-' + secondId).click();
      expect((await publishSecond).status()).toBe(200);
      await page.bringToFront(); await page.goto('/ga-e2e-testimonial-pending');
      await expect(page.getByText('GA second public testimonial.')).toBeVisible();

      await admin.bringToFront();
      const rollback = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/pages/' + approvedId + '/rollback') && r.request().method() === 'POST');
      await editor.getByTestId('managed-page-rollback-' + approvedId).click();
      expect((await rollback).status()).toBe(200);
      await page.bringToFront(); await page.goto('/ga-e2e-testimonial-pending');
      await expect(page.getByText('GA pending feedback body.')).toBeVisible();
      await expect(page.getByText('GA second public testimonial.')).toHaveCount(0);

      mode('commerce_only');
      expect((await page.goto('/ga-e2e-testimonial-pending'))?.status()).toBe(404);
    } finally {
      mode('hybrid');
      await releaseAdmin(admin);
      await admin.close();
    }
  } finally {
    mode('hybrid');
  }
});
