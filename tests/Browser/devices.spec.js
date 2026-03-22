import { test, expect } from '@playwright/test';

test.describe('Devices', () => {
    test('devices page shows empty state when no devices', async ({ page }) => {
        const uniqueEmail = `test-devices-${Date.now()}@example.com`;

        // Register a fresh user
        await page.goto('/register');
        await page.fill('#name', 'Devices Test User');
        await page.fill('#email', uniqueEmail);
        await page.fill('#password', 'password123!');
        await page.fill('#password_confirmation', 'password123!');
        await page.click('button[type="submit"]');
        await page.waitForURL('**/');

        // Navigate to devices page
        await page.goto('/devices');

        await expect(page.locator('text=Connect your first Chief server')).toBeVisible();
    });
});
