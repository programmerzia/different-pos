import { defineConfig } from '@playwright/test';

const PORT = 8123;
const origin = `http://127.0.0.1:${PORT}`;

// The browser tests run against their own database. The dev `pos` database is never touched.
export const E2E_DATABASE = 'pos_e2e';

export default defineConfig({
    testDir: 'e2e',
    testMatch: '**/*.spec.ts',
    globalSetup: './e2e/global-setup.ts',
    // One shared database and one flow feeding the next: run in file order, one worker.
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: 'list',
    timeout: 60_000,
    expect: { timeout: 10_000 },
    use: {
        baseURL: origin,
        locale: 'en-GB',
        channel: 'chrome',
        acceptDownloads: true,
        trace: 'retain-on-failure',
    },
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${PORT}`,
        url: `${origin}/up`,
        reuseExistingServer: false,
        timeout: 120_000,
        env: {
            DB_DATABASE: E2E_DATABASE,
            APP_URL: origin,
            APP_ENV: 'local',
            QUEUE_CONNECTION: 'sync',
            PHP_CLI_SERVER_WORKERS: '4',
        },
    },
});
