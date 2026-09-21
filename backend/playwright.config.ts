import { defineConfig } from '@playwright/test';

const browserChannel = process.env.PLAYWRIGHT_CHANNEL || 'msedge';
const webServerTimeout = Number(process.env.PLAYWRIGHT_WEBSERVER_TIMEOUT_MS ?? '30000');
if (!Number.isFinite(webServerTimeout) || webServerTimeout < 1000 || webServerTimeout > 180000) {
    throw new Error('PLAYWRIGHT_WEBSERVER_TIMEOUT_MS is invalid.');
}
const websiteServerCommand = process.env.PLAYWRIGHT_WEBSITE_SERVER_COMMAND
    ?? 'npm --prefix ../website run build && npm --prefix ../website run start';

// The CI-only test runner must pass the same explicit, disposable first-outlet
// fixture opt-in to nested sitemap seed/cleanup processes as to global setup.
// Seeders separately enforce APP_ENV=testing and the isolated MySQL test DB.
if (process.env.CI === 'true') {
    process.env.MT75_FIRST_OUTLET_E2E_ENABLED = '1';
}

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['line']],
    outputDir: './storage/framework/testing/playwright',
    use: {
        baseURL: 'http://127.0.0.1:18080',
        channel: browserChannel,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    globalSetup: './tests/browser/global-setup.ts',
    globalTeardown: './tests/browser/global-teardown.ts',
    webServer: [
        {
            command: 'php artisan serve --env=testing --host=127.0.0.1 --port=18080',
            url: 'http://127.0.0.1:18080/internal/admin/pos/login',
            env: { ...process.env, MT75_BROWSER_E2E: '1' },
            reuseExistingServer: false,
            timeout: webServerTimeout,
        },
        {
            command: websiteServerCommand,
            url: 'http://127.0.0.1:13000/',
            env: { ...process.env, WEBSITE_API_TIMEOUT_MS: '12000' },
            reuseExistingServer: false,
            timeout: webServerTimeout,
        },
    ],
});