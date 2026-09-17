import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['line']],
    outputDir: './storage/framework/testing/playwright',
    use: {
        baseURL: 'http://127.0.0.1:18080',
        channel: 'msedge',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    globalSetup: './tests/browser/global-setup.ts',
    globalTeardown: './tests/browser/global-teardown.ts',
    webServer: {
        command: 'php artisan serve --env=testing --host=127.0.0.1 --port=18080',
        url: 'http://127.0.0.1:18080/internal/admin/pos/login',
        reuseExistingServer: false,
        timeout: 30_000,
    },
});
