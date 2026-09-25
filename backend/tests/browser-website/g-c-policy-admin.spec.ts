import { expect, test } from '@playwright/test';

async function releaseAdmin(page: import('@playwright/test').Page) {
  if (page.isClosed()) return;
  const home = await page.goto('http://127.0.0.1:18080/internal/admin/pos');
  if (home?.status() !== 200) return;
  const logout = page.getByTestId('logout');
  if (await logout.count() !== 1) return;
  const done = page.waitForResponse(r => r.url().endsWith('/internal/admin/auth/logout') && r.request().method() === 'POST');
  await logout.click();
  expect((await done).status()).toBe(200);
}

test('G-C protected policy editor exposes truthful approval review applicability and complete policy type controls', async ({ context }) => {
  test.setTimeout(120_000);
  const admin = await context.newPage();
  await admin.setViewportSize({ width: 390, height: 844 });
  try {
    await admin.goto('http://127.0.0.1:18080/internal/admin/pos/login');
    await admin.getByTestId('login-email').fill('e2e-platform@example.invalid');
    await admin.getByTestId('login-password').fill('SyntheticPass123!');
    await admin.getByTestId('login-submit').click();
    await admin.waitForURL(url => url.pathname === '/internal/admin/pos');

    const response = await admin.goto('http://127.0.0.1:18080/internal/admin/platform');
    expect(response?.status()).toBe(200);
    await expect(admin.getByRole('heading', { name: 'Platform Administration' })).toBeVisible();
    await admin.getByRole('button', { name: 'Content', exact: true }).click();

    const policy = admin.getByRole('heading', { name: 'Legal & policy content' }).locator('xpath=ancestor::section[1]');
    await expect(policy).toContainText('never invents legal approval');
    const types = await policy.getByLabel('Policy type').locator('option').evaluateAll(options =>
      options.map(option => (option as HTMLOptionElement).value));
    expect(types).toEqual([
      'privacy', 'terms', 'returns_refunds', 'shipping_delivery', 'warranty',
      'cookie', 'digital_services_terms', 'payment_disclosures',
    ]);
    await expect(policy.getByLabel('Policy approval state')).toBeEnabled();
    await expect(policy.getByLabel('Policy approval state')).toHaveValue('draft');
    await expect(policy.getByLabel('Policy factual review state')).toBeEnabled();
    await expect(policy.getByLabel('Policy factual review state')).toHaveValue('pending');
    await policy.getByLabel('Policy type').selectOption('cookie');
    await expect(policy.getByLabel('Policy applicability')).toHaveValue('undecided');
    await expect(policy.getByLabel('Unresolved policy decisions')).toBeVisible();
    await expect(policy.getByLabel('Professional review reference')).toBeVisible();
    expect(await admin.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
  } finally {
    await releaseAdmin(admin);
    await admin.close();
  }
});
