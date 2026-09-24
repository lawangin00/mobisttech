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
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\W07BrandingE2eCleanupSeeder', '--env=testing', '--force'], { cwd: process.cwd(), stdio: 'inherit' });
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\W07MediaE2eCleanupSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit',
  });
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

test('W07 media Admin upload alt separate replacement and unused-only deletion', async ({ page, context }) => {
  test.setTimeout(150_000);
  const firstBytes = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=', 'base64');
  const secondBytes = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEklEQVR4nGMQmhUlNCuKAUIBABuWBBkXZEqoAAAAAElFTkSuQmCC', 'base64');
  const admin = await context.newPage();
  try {
    await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
    await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
    await admin.getByTestId('login-password').fill('SyntheticPass123!');
    await admin.getByTestId('login-submit').click();
    await admin.waitForURL('**/internal/admin/pos');
    await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
    await admin.getByRole('button', { name: 'Content', exact: true }).click();
    const library = admin.getByRole('heading', { name: 'Website media library' }).locator('xpath=ancestor::section[1]');
    const uploaded = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/media') && r.request().method() === 'POST');
    await library.locator('input[type="file"]').first().setInputFiles({ name: 'mt75-w07-media-first.png', mimeType: 'image/png', buffer: firstBytes });
    const firstResponse = await uploaded;
    expect(firstResponse.status()).toBe(200);
    const firstId: number = (await firstResponse.json()).data.id;
    const firstRow = library.getByTestId(`website-media-${firstId}`);
    await expect(firstRow).toContainText('mt75-w07-media-first.png');
    await firstRow.getByRole('textbox', { name: `Media alt ${firstId}` }).fill('W07 accessible image');
    const altSaved = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/media/${firstId}/alt`) && r.request().method() === 'PATCH');
    await firstRow.getByRole('button', { name: 'Save alt text' }).click();
    expect((await altSaved).status()).toBe(200);
    await expect(firstRow.getByRole('textbox', { name: `Media alt ${firstId}` })).toHaveValue('W07 accessible image');
    const replaced = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/media/${firstId}/replacement`) && r.request().method() === 'POST');
    await firstRow.getByLabel(`Replace media ${firstId}`).setInputFiles({ name: 'mt75-w07-media-second.png', mimeType: 'image/png', buffer: secondBytes });
    const response = await replaced;
    expect(response.status()).toBe(200);
    const replacement: number = (await response.json()).data.replacement.id;
    expect(replacement).not.toBe(firstId);
    await expect(firstRow).toContainText('active');
    const secondRow = library.getByTestId(`website-media-${replacement}`);
    await expect(secondRow).toContainText('mt75-w07-media-second.png');
    admin.on('dialog', dialog => dialog.accept());
    const deleted = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/media/${firstId}`) && r.request().method() === 'DELETE');
    await firstRow.getByRole('button', { name: 'Delete unused' }).click();
    const firstDeleted = await deleted;
    expect(firstDeleted.status()).toBe(200);
    expect((await firstDeleted.json()).data.retired).toBe(true);
    await expect(firstRow).toContainText('retired');
    await expect(secondRow).toContainText('active');
    const finalDeleted = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/media/${replacement}`) && r.request().method() === 'DELETE');
    await secondRow.getByRole('button', { name: 'Delete unused' }).click();
    expect((await finalDeleted).status()).toBe(200);
    await expect(secondRow).toContainText('retired');
    await page.goto('/');
    await expect(page.getByRole('heading', { name: 'MT54 managed homepage' })).toBeVisible();
  } finally {
    await releaseAdmin(admin);
    await admin.close();
  }
});

test('W07 protected branding draft, public image gate, fallback and rollback', async ({ page, context }) => {
  test.setTimeout(360_000);
  const bytes = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=', 'base64');
  await page.goto('/');
  await expect(page.locator('header img').first()).toHaveAttribute('src', '/brand/mobist-wordmark.svg');
  const admin = await context.newPage();
  try {
    await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
    await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
    await admin.getByTestId('login-password').fill('SyntheticPass123!');
    await admin.getByTestId('login-submit').click();
    await admin.waitForURL('**/internal/admin/pos');
    await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
    await admin.getByRole('button', { name: 'Content', exact: true }).click();
    const library = admin.getByRole('heading', { name: 'Website media library' }).locator('xpath=ancestor::section[1]');
    const upload = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/media') && r.request().method() === 'POST');
    await library.locator('input[type="file"]').first().setInputFiles({ name: 'mt75-w07-media-brand.png', mimeType: 'image/png', buffer: bytes });
    const uploaded = await upload;
    expect(uploaded.status()).toBe(200);
    const id: number = (await uploaded.json()).data.id;
    const path = `/branding/header_logo/${id}`;
    expect((await page.request.get(path)).status()).toBe(404);
    await admin.getByRole('button', { name: 'Website', exact: true }).click();
    const editor = admin.getByRole('region', { name: 'Website branding editor' });
    await editor.getByLabel('Website branding header_logo').selectOption(String(id));
    await editor.getByLabel('Website branding social_image').selectOption(String(id));
    await expect(editor.getByLabel('Private branding preview')).toContainText(`private image #${id}`);
    const save = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/presentation/draft') && r.request().method() === 'POST');
    await editor.getByRole('button', { name: 'Save private branding draft' }).click();
    const draft = await save;
    expect(draft.status()).toBe(200);
    const firstId: number = (await draft.json()).data.id;
    await page.goto('/');
    await expect(page.locator('header img').first()).toHaveAttribute('src', '/brand/mobist-wordmark.svg');
    expect((await page.request.get(path)).status()).toBe(404);
    const revisions = admin.getByRole('heading', { name: 'Website presentation, branding, theme, navigation & SEO' }).locator('xpath=ancestor::section[1]');
    const published = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${firstId}/publish`) && r.request().method() === 'POST');
    await revisions.getByRole('button', { name: 'Publish', exact: true }).click();
    expect((await published).status()).toBe(200);
    await page.goto('/');
    await expect(page.locator('header img').first()).toHaveAttribute('src', path);
    expect((await page.request.get(path)).status()).toBe(200);
    expect((await page.request.get(`/branding/footer_logo/${id}`)).status()).toBe(404);
    await expect(page.locator('meta[property="og:image"]')).toHaveAttribute('content', new RegExp(`/branding/social_image/${id}$`));
    for (const value of ['digital_only', 'commerce_only', 'hybrid'] as const) {
      mode(value);
      await page.goto('/');
      await expect(page.locator('header img').first()).toHaveAttribute('src', path);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
    await editor.getByLabel('Website branding header_logo').selectOption('');
    await editor.getByLabel('Website branding social_image').selectOption('');
    const resetSave = admin.waitForResponse(r => r.url().endsWith('/internal/admin/platform/presentation/draft') && r.request().method() === 'POST');
    await editor.getByRole('button', { name: 'Save private branding draft' }).click();
    const reset = await resetSave;
    expect(reset.status()).toBe(200);
    const resetId: number = (await reset.json()).data.id;
    await page.goto('/');
    await expect(page.locator('header img').first()).toHaveAttribute('src', path);
    const fallback = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${resetId}/publish`) && r.request().method() === 'POST');
    await revisions.getByRole('button', { name: 'Publish', exact: true }).click();
    expect((await fallback).status()).toBe(200);
    await page.goto('/');
    await expect(page.locator('header img').first()).toHaveAttribute('src', '/brand/mobist-wordmark.svg');
    expect((await page.request.get(path)).status()).toBe(404);
    const rolled = admin.waitForResponse(r => r.url().endsWith(`/internal/admin/platform/presentation/${firstId}/rollback`) && r.request().method() === 'POST');
    await revisions.getByRole('button', { name: 'Rollback', exact: true }).last().click();
    expect((await rolled).status()).toBe(200);
    await page.goto('/');
    await expect(page.locator('header img').first()).toHaveAttribute('src', path);
    expect((await page.request.get(path)).status()).toBe(200);
  } finally {
    await releaseAdmin(admin);
    await admin.close();
  }
});
