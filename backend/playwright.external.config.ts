import { defineConfig } from '@playwright/test';

const browserChannel = process.env.PLAYWRIGHT_CHANNEL || 'msedge';

export default defineConfig({
    testDir: './tests/browser',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: [['line']],
    use: {
        baseURL: 'http://127.0.0.1:18080',
        channel: browserChannel,
    },
});
