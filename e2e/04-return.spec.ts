import { expect, test } from '@playwright/test';
import { open, recall, remember, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('returns one unit of a completed sale', async ({ page }) => {
    const saleNumber = recall('cashSale');
    await open(page, '/app/returns/create');

    const search = page.getByLabel(/Invoice no., customer name/);
    await expect(search).toBeFocused();
    await page.keyboard.type(saleNumber);
    await expect(
        page.getByRole('button', { name: new RegExp(saleNumber) }),
    ).toBeVisible();
    await page.keyboard.press('Enter');

    const qty = page.getByLabel(/Quantity for E2E Tee/);
    await expect(qty).toBeVisible();
    await qty.focus();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('1');
    await page.keyboard.press('Control+Enter');

    await expect(page).toHaveURL(/\/app\/returns\/\d+/);
    const heading = page.locator('h1').first();
    await expect(heading).toContainText(/R[A-Z]*-?\d|RET/i);
    remember({ returnNo: ((await heading.textContent()) ?? '').trim() });

    await open(page, '/app/returns');
    await expect(page.getByRole('row').nth(1)).toContainText(saleNumber);
});

test('the ledger wizard finds the sale and walks to the confirm step', async ({
    page,
}) => {
    // The cash sale's only unit went back above; the due sale (03) still has its unit.
    const saleNumber = recall('dueSale');
    await open(page, '/app/returns');
    await page.keyboard.press('n');
    const wizard = page.getByRole('dialog');
    await expect(wizard).toContainText('step 1 of 4');
    const invoice = wizard.getByLabel(/Scan or enter the sale invoice number/);
    await expect(invoice).toBeFocused();
    await page.keyboard.type(saleNumber);
    await expect(
        wizard.getByRole('button', { name: new RegExp(saleNumber) }),
    ).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(wizard.getByText('Completed sale')).toBeVisible();
    await page.keyboard.press('Control+Enter');
    await expect(wizard).toContainText('step 2 of 4');
    await page.keyboard.press('Alt+a');
    await page.keyboard.press('Control+Enter');
    await expect(wizard).toContainText('step 3 of 4');
    await page.keyboard.press('Control+Enter');
    await expect(wizard).toContainText('step 4 of 4');
    await expect(wizard.getByText(saleNumber)).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(wizard).toBeHidden();
});
