import { defineConfig } from '@playwright/test';

// The isolated Laravel test server is started and stopped by the owned helper.
// Playwright owns only the matching local Next.js server for this bounded run.
export default defineConfig({
    testDir: './tests/browser',
    testMatch: 'mt75-fresh-next-smoke.spec.ts',
    workers: 1, retries: 0, reporter: [['line']],
    outputDir: './storage/framework/testing/playwright-mt75-next',
    use: { baseURL: 'http://127.0.0.1:18080', channel: 'msedge',
        trace: 'retain-on-failure', screenshot: 'only-on-failure' },
    webServer: { command: 'npm --prefix ../website run start',
        url: 'http://127.0.0.1:13000/', reuseExistingServer: false, timeout: 45000,
        env: { ...process.env, WEBSITE_API_TIMEOUT_MS: '12000' } },
});
