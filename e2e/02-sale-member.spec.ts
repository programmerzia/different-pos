import { expect, test } from '@playwright/test';
import { open, remember, ringUp, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('sells to a card member, paying cash in full', async ({ page }) => {
    await ringUp(page, 'E2E-TEE', '01711000001', 'Rina Member');
    await page.keyboard.press('F10');

    const done = page.getByRole('heading', { name: 'Sale completed' });
    await expect(done).toBeVisible();
    const number =
        (
            await page
                .locator('h2:has-text("Sale completed") + p')
                .textContent()
        )?.trim() ?? '';
    expect(number).not.toBe('');
    remember({ cashSale: number });

    await open(page, '/app/sales');
    await expect(
        page.getByRole('row', { name: new RegExp(number) }),
    ).toBeVisible();
});

test('Shift+F10 on a sales row opens its context menu', async ({ page }) => {
    await open(page, '/app/sales');
    const row = page.getByRole('row').nth(1);
    await row.focus();
    await page.keyboard.press('Shift+F10');
    const menu = page.getByRole('menu');
    await expect(menu).toBeVisible();
    await expect(
        menu.getByRole('menuitem', { name: 'Open', exact: true }),
    ).toBeVisible();
    await expect(menu.getByRole('menuitem', { name: /void/i })).toBeVisible();
    await expect(
        menu.getByRole('menuitem', { name: /exchange/i }),
    ).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
});
