import { expect, test } from '@playwright/test';
import { open, ringUp, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('buys a new raw material on a GRN, and the register cannot sell it', async ({
    page,
}) => {
    await open(page, '/app/purchases/create');
    const vendor = page.getByRole('combobox', { name: /^Vendor/ });
    await vendor.focus();
    await page.keyboard.type('E2E Textile');
    await page.keyboard.press('Enter');
    await expect(vendor).toHaveValue('E2E Textile Mills');

    await page.getByRole('button', { name: /New item/ }).first().click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel(/Product Name \(English\)/).fill('E2E Cotton Fabric');
    await dialog.getByLabel(/Unit of Measure/).selectOption({ label: 'Metre (m)' });
    await dialog.getByLabel(/Cost per unit/).fill('250');
    await dialog.getByRole('button', { name: /Create and add/ }).click();
    await expect(dialog).toBeHidden();

    await expect(
        page.getByRole('rowheader', { name: /E2E Cotton Fabric/ }),
    ).toBeVisible();
    await expect(page.getByText('Not sold').first()).toBeVisible();
    await page.keyboard.press('Control+Enter');
    await expect(page).toHaveURL(/\/app\/purchases\/[0-9a-f-]{36}$/);
    await expect(page.getByText('E2E Cotton Fabric').first()).toBeVisible();

    await open(page, '/app/pos');
    await page.keyboard.press('F2');
    const scan = page.getByLabel(/Scan barcode, SKU/);
    await expect(scan).toBeFocused();
    await scan.fill('E2E Cotton Fabric');
    await page.waitForTimeout(800);
    await expect(page.getByText('E2E Cotton Fabric')).toHaveCount(0);
});

test('adds a brand from the product form without losing what was typed', async ({
    page,
}) => {
    await open(page, '/app/products/create');
    const name = page.getByLabel(/Product Name \(English\)/);
    await name.fill('E2E Branded Shirt');

    await page.getByRole('button', { name: /Add brand/ }).click();
    const dialog = page.getByRole('dialog', { name: /New brand/ });
    await dialog.getByLabel(/Brand name/).fill('E2E House');
    await dialog.getByRole('button', { name: /Save|Add/ }).last().click();
    await expect(dialog).toBeHidden();

    await expect(page.locator('#product-brand_id option:checked')).toHaveText(
        'E2E House',
    );
    await expect(name).toHaveValue('E2E Branded Shirt');
});

test('the default courier prints on the chalan of a new sale', async ({
    page,
}) => {
    await open(page, '/app/settings/couriers');
    await page.getByRole('button', { name: /New courier/ }).first().click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel(/^Name/).fill('E2E Express');
    await dialog.getByLabel(/Default courier/).check();
    await dialog.getByRole('button', { name: /Save/ }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByText('E2E Express').first()).toBeVisible();

    await ringUp(page, 'E2E-TEE', '01711000001', 'Rina Member');
    await page.keyboard.press('F10');
    await expect(
        page.getByRole('heading', { name: 'Sale completed' }),
    ).toBeVisible();
    const [chalan] = await Promise.all([
        page.waitForEvent('popup'),
        page.getByRole('button', { name: /Delivery chalan/ }).click(),
    ]);
    await expect(chalan.locator('.label-fields')).toContainText('E2E Express');
    await chalan.close();
});
