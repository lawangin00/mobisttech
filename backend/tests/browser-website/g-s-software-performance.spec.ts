import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

type Mode = 'hybrid' | 'digital_only' | 'commerce_only';

function base(name: string) {
    execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\' + name, '--env=testing', '--force'], {
        cwd: process.cwd(), stdio: 'inherit',
    });
}

function mode(value: Mode) {
    execFileSync('php', ['artisan', 'db:seed', '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder', '--env=testing', '--force'], {
        cwd: process.cwd(), stdio: 'inherit', env: { ...process.env, WEBSITE_E2E_MODE: value, WEBSITE_E2E_ACTION: '' },
    });
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => base('DynamicWebsiteE2eSeeder'));
test.afterAll(() => {
    try { mode('hybrid'); } finally { base('DynamicWebsiteE2eCleanupSeeder'); }
});

test('G-S published Software overview stays responsive within fixed mobile budgets in every Website mode', async ({ page }) => {
    test.setTimeout(180_000);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.addInitScript(() => {
        const probe = { lcp: 0, cls: 0, maxEvent: 0 };
        (window as typeof window & { __gsSoftwarePerf?: typeof probe }).__gsSoftwarePerf = probe;
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
        for (const activeMode of ['hybrid', 'digital_only', 'commerce_only'] as Mode[]) {
            mode(activeMode);
            const samples: Array<{ lcp: number; cls: number; maxEvent: number }> = [];
            for (let attempt = 0; attempt < 3; attempt++) {
                expect((await page.goto('/software/mt54-software?gs-perf=' + activeMode + '-' + attempt, { waitUntil: 'networkidle' }))?.status()).toBe(200);
                await expect(page.getByRole('heading', { name: 'MT54 Software' })).toBeVisible();
                expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2)).toBe(true);
                await page.waitForTimeout(80);
                const metric = await page.evaluate(() =>
                    (window as typeof window & { __gsSoftwarePerf?: { lcp: number; cls: number; maxEvent: number } }).__gsSoftwarePerf);
                expect(metric).toBeTruthy();
                samples.push(metric!);
            }
            const lcp = samples.map(item => item.lcp).sort((a, b) => a - b)[1];
            const cls = Math.max(...samples.map(item => item.cls));
            const maxEvent = Math.max(...samples.map(item => item.maxEvent));
            console.log('GS_SOFTWARE_PERF ' + activeMode + ' ' + JSON.stringify({ lcp, cls, maxEvent }));
            expect(lcp).toBeGreaterThan(0);
            expect(lcp).toBeLessThanOrEqual(2500);
            expect(cls).toBeLessThanOrEqual(0.1);
            expect(maxEvent).toBeLessThanOrEqual(200);
        }
    } finally {
        mode('hybrid');
    }
});
