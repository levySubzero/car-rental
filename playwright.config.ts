import { defineConfig, devices } from '@playwright/test';

// End-to-end tests run against a running, seeded app (`composer dev`). CI runs only the
// axe spec (tests/e2e/a11y.spec.ts), against a seeded SQLite app (the a11y job).
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        trace: 'on-first-retry',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile', use: { ...devices['Pixel 7'] } },
    ],
});
