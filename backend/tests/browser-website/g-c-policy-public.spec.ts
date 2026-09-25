import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function policies(action: 'seed' | 'cleanup') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\GCLegalPolicyBrowserSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit',
    env: { ...process.env, MT75_GC_POLICY_E2E: '1', MT75_GC_POLICY_ACTION: action },
  });
}

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => policies('seed'));
test.afterAll(() => policies('cleanup'));

test('G-C published policy footer exposes every applicable consolidated policy route without cookie fabrication', async ({ page }) => {
  test.setTimeout(90_000);
  await page.setViewportSize({ width: 390, height: 844 });
  expect((await page.goto('/'))?.status()).toBe(200);
  const footer = page.getByRole('navigation', { name: 'Policies' });
  const expected = [
    ['Privacy Policy', '/privacy-policy'],
    ['Terms & Conditions', '/terms-conditions'],
    ['Return & Refund Policy', '/return-refund-policy'],
    ['Shipping / Delivery Policy', '/shipping-delivery-policy'],
    ['Warranty Policy', '/warranty-policy'],
    ['Digital Services / Project Terms', '/digital-services-terms'],
    ['Payment Disclosures', '/payment-disclosures'],
  ] as const;
  for (const [label, href] of expected) {
    await expect(footer.getByRole('link', { name: label })).toHaveAttribute('href', href);
    expect((await page.goto(href))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: label })).toBeVisible();
    await expect(page.getByText('Version 1 · Effective 2026-09-25')).toBeVisible();
  }
  await page.goto('/');
  await expect(footer.getByRole('link', { name: 'Cookie Policy' })).toHaveCount(0);
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
});
