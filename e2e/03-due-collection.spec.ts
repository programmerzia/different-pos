import { expect, test } from '@playwright/test';
import { open, remember, ringUp, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('leaves the whole bill as due, then collects it from the dues screen', async ({
    page,
}) => {
    await ringUp(page, 'E2E-CAP', '01711000002', 'Karim Regular');
    await page.keyboard.press('Alt+D');
    await page.keyboard.press('F10');
    await expect(
        page.getByRole('heading', { name: 'Sale completed' }),
    ).toBeVisible();
    const number =
        (
            await page
                .locator('h2:has-text("Sale completed") + p')
                .textContent()
        )?.trim() ?? '';
    remember({ dueSale: number });

    await open(page, '/app/dues');
    const row = page.getByRole('row', { name: /Karim Regular/ });
    await expect(row).toBeVisible();
    await row.focus();
    await page.keyboard.press('Enter');

    await expect(page).toHaveURL(/\/app\/dues\/.+/);
    await expect(
        page.getByRole('heading', { name: 'Karim Regular', level: 1 }),
    ).toBeVisible();
    await page.keyboard.press('c');
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(dialog).toBeHidden();

    await open(page, '/app/dues');
    await expect(page.getByRole('row', { name: /Karim Regular/ })).toHaveCount(
        0,
    );
});
