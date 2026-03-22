import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
    test('landing page shows for unauthenticated users', async ({ page }) => {
        await page.goto('/');

        await expect(page.locator('text=Remote control for Chief')).toBeVisible();
        await expect(page.locator('a[href="/login"]').first()).toBeVisible();
        await expect(page.locator('a[href="/register"]').first()).toBeVisible();
    });

    test('login page renders with GitHub OAuth button', async ({ page }) => {
        await page.goto('/login');

        await expect(page.locator('text=Sign in to Chief Uplink')).toBeVisible();
        await expect(page.locator('#email')).toBeVisible();
        await expect(page.locator('#password')).toBeVisible();
        await expect(page.locator('a[href="/auth/github"]')).toBeVisible();
    });

    test('registration flow completes and reaches dashboard', async ({ page }) => {
        const uniqueEmail = `test-${Date.now()}@example.com`;

        await page.goto('/register');

        await expect(page.locator('text=Create your account')).toBeVisible();

        await page.fill('#name', 'Test User');
        await page.fill('#email', uniqueEmail);
        await page.fill('#password', 'password123!');
        await page.fill('#password_confirmation', 'password123!');

        await page.click('button[type="submit"]');

        await page.waitForURL('**/');
        await expect(page.locator('text=Welcome to Chief Uplink')).toBeVisible();
    });
});
