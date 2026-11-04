import { expect, test, type Page } from '@playwright/test';
import { open, ringUp, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await page.setViewportSize({ width: 1366, height: 768 });
    await signIn(page);
});

/** Sells one unit of a SKU to the card member for cash and returns the invoice number. */
async function sell(page: Page, sku: string): Promise<string> {
    await ringUp(page, sku, '01711000001', 'Rina Member');
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
    expect(number).not.toBe('');

    return number;
}

/** Opens the exchange screen on a sale and takes one unit of its line back. */
async function startExchange(page: Page, number: string, lineName: string) {
    await open(page, '/app/exchanges/create');
    const search = page.getByLabel(/Invoice no., customer name/);
    await expect(search).toBeFocused();
    await page.keyboard.type(number);
    await expect(
        page.getByRole('button', { name: new RegExp(number) }),
    ).toBeVisible();
    await page.keyboard.press('Enter');
    const qty = page.getByLabel(new RegExp(`Quantity for ${lineName}`));
    await expect(qty).toBeVisible();
    await qty.focus();
    await page.keyboard.press('Control+A');
    await page.keyboard.type('1');
}

async function pick(page: Page, term: string, name: RegExp) {
    const search = page.getByLabel('Search products by name, SKU or barcode');
    await search.focus();
    await page.keyboard.type(term);
    await expect(
        page
            .getByRole('list', { name: 'Products' })
            .getByRole('button', { name }),
    ).toBeVisible();
    await page.keyboard.press('Enter');
}

/** The saved exchange offers its documents; both tabs open on the replacement sale. */
async function expectDocuments(page: Page) {
    await expect(page).toHaveURL(/\/app\/returns\/\d+/);
    const section = page.getByRole('region', { name: 'Replacement sale' });
    const number =
        (await section.getByRole('link').textContent())?.trim() ?? '';
    expect(number).not.toBe('');
    const invoice = page.getByRole('button', { name: /Print new invoice/ });
    const chalan = page.getByRole('button', { name: /Delivery chalan/ });
    await expect(
        page.getByRole('button', { name: /Print return receipt/ }),
    ).toBeVisible();
    const [invoiceTab] = await Promise.all([
        page.waitForEvent('popup'),
        invoice.click(),
    ]);
    await expect(invoiceTab.locator('body')).toContainText(number);
    await invoiceTab.close();
    const [chalanTab] = await Promise.all([
        page.waitForEvent('popup'),
        chalan.click(),
    ]);
    await expect(chalanTab.locator('body')).toContainText(`CH-${number}`);
    await chalanTab.close();
}

test('a dearer swap shows what the customer pays, then saves', async ({
    page,
}) => {
    const number = await sell(page, 'E2E-TEE');
    await startExchange(page, number, 'E2E Tee');

    // Nothing to give back or pay until both sides exist.
    const summary = page.getByRole('region', { name: 'Summary' });
    await expect(summary).toContainText('Add what the customer takes instead');
    await expect(summary).not.toContainText('Customer gets back');

    await pick(page, 'E2E Bag', /E2E Bag/);
    await expect(
        summary.locator('[data-test="settlement-summary"]'),
    ).toHaveText('Customer pays');
    await expect(summary).toContainText('৳750.00');
    await expect(page.getByLabel('Amount received')).toHaveValue('750.00');
    // The summary and Save stay on screen on a laptop.
    const save = page.getByRole('button', { name: /Save exchange/ });
    await expect(save).toBeInViewport();
    await expect(save).toBeEnabled();

    // A part payment leaves the rest on the named customer's account.
    await page.getByLabel('Amount received').fill('700');
    await expect(page.locator('[data-test="tender-on-account"]')).toContainText(
        '৳50.00',
    );
    await page.getByLabel('Amount received').fill('750');
    await page.keyboard.press('Control+Enter');
    await expectDocuments(page);
});

test('a cheaper swap shows what the customer gets back, then saves', async ({
    page,
}) => {
    const number = await sell(page, 'E2E-TEE');
    await startExchange(page, number, 'E2E Tee');
    await pick(page, 'E2E Cap', /E2E Cap/);
    const summary = page.getByRole('region', { name: 'Summary' });
    await expect(
        summary.locator('[data-test="settlement-summary"]'),
    ).toHaveText('Customer gets back');
    await expect(summary).toContainText('৳150.00');
    await expect(page.getByLabel('Amount received')).toHaveCount(0);
    await expect(page.getByLabel('Give the difference back as')).toBeVisible();
    await page.keyboard.press('Control+Enter');
    await expectDocuments(page);
});

test('an even swap saves with nothing to pay', async ({ page }) => {
    const number = await sell(page, 'E2E-TEE');
    await startExchange(page, number, 'E2E Tee');
    await pick(page, 'E2E Tee', /E2E Tee/);
    const summary = page.getByRole('region', { name: 'Summary' });
    await expect(
        summary.locator('[data-test="settlement-summary"]'),
    ).toHaveText('Even exchange');
    await expect(page.getByLabel('Amount received')).toHaveCount(0);
    await page.keyboard.press('Control+Enter');
    await expectDocuments(page);
});

test('raw materials are not offered and an out-of-stock product cannot be added', async ({
    page,
}) => {
    const number = await sell(page, 'E2E-TEE');
    await startExchange(page, number, 'E2E Tee');

    const search = page.getByLabel('Search products by name, SKU or barcode');
    await search.focus();
    await page.keyboard.type('Seeded');
    const list = page.getByRole('list', { name: 'Products' });
    const soldOut = list.getByRole('button', { name: /Seeded Sold-Out Tee/ });
    await expect(soldOut).toBeVisible();
    await expect(soldOut).toContainText('Out of stock');
    await expect(list.getByText('Seeded Linen Roll')).toHaveCount(0);

    // Neither Enter nor a click puts it in the cart, and Save stays off.
    await page.keyboard.press('Enter');
    await soldOut.click({ force: true });
    await expect(
        page.getByText('Add the items the customer takes instead.'),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: /Save exchange/ }),
    ).toBeDisabled();

    // A raw material's SKU finds nothing.
    await search.fill('E2E-RAW');
    await expect(page.getByText('No product matches.')).toBeVisible();
});
