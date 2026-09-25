import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function seed(name: string, action = '') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + name, '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env,
      MT75_GA_SERVICE_LANDING_E2E: '1', MT75_GA_SERVICE_LANDING_ACTION: action },
  });
}
function mode(value: 'hybrid' | 'digital_only' | 'commerce_only') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, WEBSITE_E2E_MODE: value, WEBSITE_E2E_ACTION: '' },
  });
}
test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { seed('DynamicWebsiteE2eSeeder'); seed('GADigitalLandingBrowserSeeder', 'seed'); });
test.afterAll(() => {
  try { seed('GADigitalLandingBrowserSeeder', 'cleanup'); }
  finally { seed('DynamicWebsiteE2eCleanupSeeder'); }
});
test('G-A actual published linked service landing, related case and mode-scoped private draft', async ({ page }) => {
  test.setTimeout(150_000);
  await page.setViewportSize({ width: 390, height: 844 });
  try {
    expect((await page.goto('/services/mt54-web-development'))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'GA rich published service' })).toBeVisible();
    await expect(page.getByText('GA published service body.')).toBeVisible();
    await expect(page.getByText('GA identified customer problem')).toBeVisible();
    await expect(page.getByText('Secure service feature')).toBeVisible();
    await expect(page.getByText('GA delivery')).toBeVisible();
    await expect(page.getByText('GA discovery')).toBeVisible();
    await expect(page.getByText('GA service question?')).toBeVisible();
    await expect(page.getByRole('link', { name: 'GA related case study' })).toHaveAttribute('href', '/ga-e2e-related-case');
    await expect(page).toHaveTitle(/GA authored service SEO/);
    await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', 'GA managed service description');
    await expect(page.getByText('GA_PRIVATE_UNPUBLISHED_BODY')).toHaveCount(0);
    await expect(page.getByText('GA_HIDDEN_INTERNAL_VALUE')).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 2)).toBe(true);
    await page.getByRole('link', { name: 'GA related case study' }).click();
    await expect(page.getByText('GA case details')).toBeVisible();
    await expect(page.getByTestId('case-disclosure')).toContainText('Anonymous case study');
    await expect(page.getByText('GA case challenge')).toBeVisible();
    await expect(page.getByText('GA case solution')).toBeVisible();
    await expect(page.getByText('GA measured outcome')).toBeVisible();
    await expect(page.getByText('GA_HIDDEN_CASE_INDUSTRY')).toHaveCount(0);
    mode('commerce_only');
    expect((await page.goto('/services/mt54-web-development'))?.status()).toBe(404);
    mode('digital_only');
    expect((await page.goto('/services/mt54-web-development'))?.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'GA rich published service' })).toBeVisible();
  } finally { mode('hybrid'); }
});

// This probes only the changed Digital Service renderer; the pre-existing all-mode homepage
// Lighthouse/request acceptance is reused from MT-7.2 and is not rerun here.
test('G-A changed service route production mobile performance in both active digital modes', async ({ page }) => {
  test.setTimeout(150_000);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.addInitScript(() => {
    const probe = { lcp: 0, cls: 0, maxEvent: 0 };
    (window as typeof window & { __gaPerf?: typeof probe }).__gaPerf = probe;
    new PerformanceObserver(entries => {
      for (const entry of entries.getEntries()) probe.lcp = Math.max(probe.lcp, entry.startTime);
    }).observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver(entries => {
      for (const entry of entries.getEntries()) {
        const shift = entry as PerformanceEntry & { value?: number; hadRecentInput?: boolean };
        if (!shift.hadRecentInput) probe.cls += shift.value ?? 0;
      }
    }).observe({ type: 'layout-shift', buffered: true });
    try {
      new PerformanceObserver(entries => {
        for (const entry of entries.getEntries()) probe.maxEvent = Math.max(probe.maxEvent, entry.duration);
      }).observe({ type: 'event', buffered: true, durationThreshold: 16 } as PerformanceObserverInit);
    } catch { /* older test browser: interaction remains unmeasured, never field INP */ }
  });
  try {
    for (const value of ['hybrid', 'digital_only'] as const) {
      mode(value);
      await page.goto('/services/mt54-web-development?ga-warm=' + value, { waitUntil: 'networkidle' });
      const samples: number[] = []; const cls: number[] = []; const events: number[] = [];
      for (let attempt = 0; attempt < 3; attempt++) {
        expect((await page.goto('/services/mt54-web-development?ga=' + value + attempt, { waitUntil: 'networkidle' }))?.status()).toBe(200);
        const faq = page.getByText('GA service question?');
        await faq.click();
        await page.waitForTimeout(80);
        const metric = await page.evaluate(() => (window as typeof window & { __gaPerf?: { lcp: number; cls: number; maxEvent: number } }).__gaPerf);
        expect(metric).toBeTruthy();
        samples.push(metric!.lcp); cls.push(metric!.cls); events.push(metric!.maxEvent);
      }
      const median = [...samples].sort((a,b) => a-b)[1];
      console.log('GA_SERVICE_PERF ' + value + ' ' + JSON.stringify({ samples, medianLcp: median, maxCls: Math.max(...cls), maxLabEvent: Math.max(...events) }));
      expect(median).toBeGreaterThan(0);
      expect(median).toBeLessThanOrEqual(2500);
      expect(Math.max(...cls)).toBeLessThanOrEqual(0.1);
      expect(Math.max(...events)).toBeLessThanOrEqual(200);
    }
  } finally { mode('hybrid'); }
});
