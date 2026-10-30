import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: 'e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: 'list',
    use: {
        baseURL: process.env.APP_URL ?? 'http://localhost:8000',
        trace: 'on-first-retry',
    },
});
