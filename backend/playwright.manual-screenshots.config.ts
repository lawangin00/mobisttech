import { defineConfig } from '@playwright/test';

const browserChannel = process.env.PLAYWRIGHT_CHANNEL || 'msedge';
const webServerTimeout = Number(process.env.PLAYWRIGHT_WEBSERVER_TIMEOUT_MS ?? '120000');

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['line']],
    outputDir: './storage/framework/testing/manual-screenshots',
    use: {
        baseURL: 'http://127.0.0.1:18080',
        channel: browserChannel,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    globalSetup: './tests/browser/global-setup.ts',
    globalTeardown: './tests/browser/global-teardown.ts',
    webServer: [{
        command: 'php artisan serve --env=testing --host=127.0.0.1 --port=18080',
        url: 'http://127.0.0.1:18080/internal/admin/pos/login',
        env: { ...process.env, MT75_BROWSER_E2E: '1' },
        reuseExistingServer: false,
        timeout: webServerTimeout,
    }],
});
