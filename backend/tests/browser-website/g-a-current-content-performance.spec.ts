import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function knowledge(action = '') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\GAKnowledgeBrowserSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, MT75_GA_KNOWLEDGE_E2E: '1', MT75_GA_KNOWLEDGE_ACTION: action },
  });
}
function cases(action = '') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\GACaseStudyBrowserSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, MT75_GA_CASE_E2E: '1', MT75_GA_CASE_ACTION: action },
  });
}
function base(name: string) {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + name, '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit',
  });
}
function mode(value: 'hybrid' | 'digital_only' | 'commerce_only') {
  execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder', '--env=testing', '--force'], {
    cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, WEBSITE_E2E_MODE: value, WEBSITE_E2E_ACTION: '' },
  });
}

test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { base('DynamicWebsiteE2eSeeder'); knowledge('seed'); cases('seed'); });
test.afterAll(() => {
  try { cases('cleanup'); } finally {
    try { knowledge('cleanup'); } finally { base('DynamicWebsiteE2eCleanupSeeder'); }
  }
});

test('G-A current changed knowledge and case routes stay within fixed mobile budgets in both digital modes', async ({ page }) => {
  test.setTimeout(180_000);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.addInitScript(() => {
    const probe = { lcp: 0, cls: 0, maxEvent: 0 };
    (window as typeof window & { __gaContentPerf?: typeof probe }).__gaContentPerf = probe;
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
    } catch { /* lab browser may not expose event timing */ }
  });

  try {
    for (const activeMode of ['hybrid', 'digital_only'] as const) {
      mode(activeMode);
      for (const route of ['/knowledge', '/ga-e2e-case-visible']) {
        const samples: Array<{ lcp: number; cls: number; maxEvent: number }> = [];
        for (let attempt = 0; attempt < 3; attempt++) {
          expect((await page.goto(route + '?ga-perf=' + activeMode + attempt, { waitUntil: 'networkidle' }))?.status()).toBe(200);
          await page.waitForTimeout(80);
          const metric = await page.evaluate(() =>
            (window as typeof window & { __gaContentPerf?: { lcp: number; cls: number; maxEvent: number } }).__gaContentPerf);
          expect(metric).toBeTruthy();
          samples.push(metric!);
        }
        const lcp = samples.map(item => item.lcp).sort((a,b) => a-b)[1];
        const cls = Math.max(...samples.map(item => item.cls));
        const maxEvent = Math.max(...samples.map(item => item.maxEvent));
        console.log('GA_CURRENT_CONTENT_PERF ' + activeMode + ' ' + route + ' ' + JSON.stringify({ lcp, cls, maxEvent }));
        expect(lcp).toBeGreaterThan(0);
        expect(lcp).toBeLessThanOrEqual(2500);
        expect(cls).toBeLessThanOrEqual(0.1);
        expect(maxEvent).toBeLessThanOrEqual(200);
      }
    }
    mode('commerce_only');
    expect((await page.goto('/knowledge'))?.status()).toBe(404);
    expect((await page.goto('/ga-e2e-case-visible'))?.status()).toBe(404);
  } finally {
    mode('hybrid');
  }
});
