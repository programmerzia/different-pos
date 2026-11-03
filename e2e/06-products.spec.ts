import { expect, test } from '@playwright/test';
import { open, signIn, tabTo } from './support';

test('adds a product in the dialog with the keyboard and edits it from the list', async ({
    page,
}) => {
    await signIn(page);
    await open(page, '/app/products');
    await expect(
        page.getByRole('heading', { name: 'Product Catalog & Inventory Hub' }),
    ).toBeVisible();

    // N opens the add dialog over the list; the URL is a deep link.
    await page.keyboard.press('n');
    await expect(page).toHaveURL(/\/app\/products\/create/);
    const dialog = page.getByRole('dialog', {
        name: /New Product Registration/,
    });
    await expect(dialog).toBeVisible();
    await expect(page.locator('#product-name')).toBeFocused();

    // Ctrl+Enter with the required fields empty shows inline errors and stays open.
    await page.keyboard.press('Control+Enter');
    await expect(dialog.getByText('Product name is required')).toBeVisible();

    await page.keyboard.type('E2E Silk Saree');
    await tabTo(page, '#product-sku', 12);
    await page.keyboard.type('E2E-SAR-1');
    await tabTo(page, '#product-sale_price', 24);
    await page.keyboard.type('1450');
    await page.keyboard.press('Control+Enter');

    await expect(dialog).toBeHidden();
    await expect(page).toHaveURL(/\/app\/products(\?|$)/);

    await page.keyboard.press('/');
    await page.keyboard.type('E2E-SAR-1');
    const row = page.locator('tbody tr', { hasText: 'E2E-SAR-1' });
    await expect(row).toBeVisible();
    await expect(page.locator('tbody tr')).toHaveCount(1);

    // Enter on the row opens the edit dialog; Esc closes it when nothing changed.
    await page.keyboard.press('Tab');
    await tabTo(page, 'tbody tr', 30);
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/app\/products\/[^/]+\/edit/);
    const edit = page.getByRole('dialog', {
        name: /Edit Product Specifications/,
    });
    await expect(edit).toBeVisible();
    await expect(page.locator('#product-name')).toHaveValue('E2E Silk Saree');
    await page.keyboard.press('Escape');
    await expect(edit).toBeHidden();
    await expect(page).not.toHaveURL(/\/edit/);

    // A changed form asks before it closes.
    await page.keyboard.press('n');
    await expect(dialog).toBeVisible();
    await page.keyboard.type('Half typed');
    await page.keyboard.press('Escape');
    const confirm = page.getByRole('dialog', { name: /Discard your changes/ });
    await expect(confirm).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(confirm).toBeHidden();
    await expect(dialog).toBeVisible();
});

test('the stock list shows branch columns, totals and the deferred actions', async ({
    page,
}) => {
    await signIn(page);
    await open(page, '/app/stock');
    await expect(
        page.getByRole('heading', { name: /Stock Management & Transfers/ }),
    ).toBeVisible();
    await expect(page.getByRole('columnheader', { name: /Total Combined/ })).toBeVisible();
    await expect(page.locator('tfoot')).toContainText(/Total \(/);
    await expect(
        page.getByRole('button', { name: /Branch Transfer/ }),
    ).toHaveAttribute('aria-disabled', 'true');
});
