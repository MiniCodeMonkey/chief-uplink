import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.APP_URL || 'http://127.0.0.1:8000',
        viewport: { width: 375, height: 812 },
        trace: 'on-first-retry',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'], viewport: { width: 375, height: 812 } },
        },
    ],
    webServer: {
        command: 'bash tests/Browser/setup.sh',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: !process.env.CI,
        cwd: '../../',
        timeout: 60000,
    },
});
