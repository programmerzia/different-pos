import { expect, test } from '@playwright/test';
import {
    chooseBranch,
    login,
    open,
    saveSession,
    signIn,
    tabTo,
} from './support';

test('signs in and picks the branch without the mouse', async ({ page }) => {
    await login(page);
    await chooseBranch(page);
    await expect(page.locator('h1').first()).toBeVisible();
    await saveSession(page);
});

test('a wrong password stays on the login page with an error', async ({
    page,
}) => {
    await page.goto('/login');
    await expect(page.locator('#login')).toBeFocused();
    await page.keyboard.type('nobody@demo.test');
    await tabTo(page, '#password');
    await page.keyboard.type('not-the-password');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/login/);
    await expect(
        page.getByText(/do not match|incorrect|invalid/i).first(),
    ).toBeVisible();
});

test('? opens the shortcut overlay and Escape closes it', async ({ page }) => {
    await signIn(page);
    await open(page, '/app/sales');
    await page.keyboard.press('Shift+?');
    const overlay = page.getByRole('dialog');
    await expect(overlay).toBeVisible();
    await expect(overlay).toContainText(/shortcut/i);
    await page.keyboard.press('Escape');
    await expect(overlay).toBeHidden();
});
