import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

type Mode = 'hybrid' | 'digital_only' | 'commerce_only';
type Sample = { lcp: number; cls: number; maxEvent: number; requests: number };

function setMode(mode: Mode) {
    execFileSync('php', [
        'artisan',
        'db:seed',
        '--class=Database\\Seeders\\WebsiteStorefrontE2eStateSeeder',
        '--env=testing',
        '--force',
    ], {
        cwd: process.cwd(),
        stdio: 'inherit',
        env: { ...process.env, WEBSITE_E2E_MODE: mode, WEBSITE_E2E_ACTION: '' },
    });
}

function median(values: number[]) {
    const sorted = [...values].sort((a, b) => a - b);
    return sorted[Math.floor(sorted.length / 2)];
}

test('MT-7.3 production performance and request budgets stay within published limits', async ({ page }) => {
    test.setTimeout(180_000);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.addInitScript(() => {
        const metrics = { lcp: 0, cls: 0, maxEvent: 0 };
        (window as typeof window & { __ciPerf?: typeof metrics }).__ciPerf = metrics;
        new PerformanceObserver(list => {
            for (const entry of list.getEntries()) metrics.lcp = Math.max(metrics.lcp, entry.startTime);
        }).observe({ type: 'largest-contentful-paint', buffered: true });
        new PerformanceObserver(list => {
            for (const entry of list.getEntries() as PerformanceEntry[]) {
                const shift = entry as PerformanceEntry & { value?: number; hadRecentInput?: boolean };
                if (!shift.hadRecentInput) metrics.cls += shift.value ?? 0;
            }
        }).observe({ type: 'layout-shift', buffered: true });
        try {
            new PerformanceObserver(list => {
                for (const entry of list.getEntries()) metrics.maxEvent = Math.max(metrics.maxEvent, entry.duration);
            }).observe({ type: 'event', buffered: true, durationThreshold: 16 } as PerformanceObserverInit);
        } catch {}
    });

    for (const mode of ['hybrid', 'digital_only', 'commerce_only'] as Mode[]) {
        setMode(mode);
        await page.goto('/?ci-warm=' + mode, { waitUntil: 'networkidle' });

        const samples: Sample[] = [];
        for (let index = 0; index < 3; index++) {
            let requests = 0;
            const count = () => { requests += 1; };
            page.on('request', count);
            await page.goto('/?ci=' + mode + '-' + index, { waitUntil: 'networkidle' });

            const link = page.locator('a').first();
            await link.evaluate(node => node.addEventListener('click', event => event.preventDefault(), { once: true }));
            await link.click();
            await page.waitForTimeout(100);
            page.off('request', count);

            const metrics = await page.evaluate(() => {
                return (window as typeof window & { __ciPerf?: Omit<Sample, 'requests'> }).__ciPerf
                    ?? { lcp: 0, cls: 0, maxEvent: 0 };
            });
            samples.push({ ...metrics, requests });
        }

        const lcp = median(samples.map(sample => sample.lcp));
        const cls = Math.max(...samples.map(sample => sample.cls));
        const maxEvent = Math.max(...samples.map(sample => sample.maxEvent));
        const requests = Math.max(...samples.map(sample => sample.requests));

        console.log('CI_PERF ' + mode + ' ' + JSON.stringify({ lcp, cls, maxEvent, requests, samples }));
        expect(lcp, mode + ' median LCP').toBeLessThanOrEqual(2500);
        expect(cls, mode + ' maximum CLS').toBeLessThanOrEqual(0.1);
        expect(maxEvent, mode + ' interaction').toBeLessThanOrEqual(200);
        expect(requests, mode + ' public request budget').toBeLessThanOrEqual(12);
    }
});
