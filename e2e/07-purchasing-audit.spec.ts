import { expect, test, type Page } from '@playwright/test';
import { open, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('the vendor directory adds a vendor in the dialog with the keyboard', async ({
    page,
}) => {
    await open(page, '/app/vendors');
    await expect(
        page.getByRole('heading', { name: 'Vendor & Supplier Directory' }),
    ).toBeVisible();

    await page.locator('h1').first().click();
    await page.keyboard.press('n');
    const dialog = page.getByRole('dialog', {
        name: /Register New Supplier \/ Vendor/,
    });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel(/^Vendor Company Name/)).toBeFocused();
    await page.keyboard.type('E2E Textile Mills');
    await dialog.getByLabel(/^Mobile \/ Phone Number/).fill('01711223344');
    await page.keyboard.press('Control+s');

    await expect(
        page.getByRole('heading', { name: 'E2E Textile Mills' }),
    ).toBeVisible();
});

test('the purchases list shows the KPI strip above the list', async ({
    page,
}) => {
    await open(page, '/app/purchases');
    await expect(
        page.getByRole('heading', { name: 'Vendor Purchases & GRN' }),
    ).toBeVisible();
    await expect(page.getByText('Total Inward Bills')).toBeVisible();
    await expect(page.getByText('Outstanding Vendor Payables')).toBeVisible();
    await expect(page.getByText('No purchases yet')).toBeVisible();
});

/** On hand and the number of purchase movements on the product's stock movements page. */
async function stockOf(page: Page, sku: string) {
    await open(page, `/app/stock?q=${sku}`);
    // Keyboard entry into the list: Alt+↓ focuses the first row, Enter opens its movements.
    await page.keyboard.press('Alt+ArrowDown');
    await expect(page.locator('[data-list-row]').first()).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/movements$/);
    const onHand = Number(
        ((await page.locator('[data-test="on-hand"]').textContent()) ?? '')
            .replace(/[^0-9.]/g, ''),
    );
    const purchases = await page
        .locator('tbody tr')
        .filter({ has: page.getByRole('cell', { name: 'Purchase', exact: true }) })
        .count();

    return { onHand, purchases };
}

test('receives a GRN from the keyboard and the stock and batches rise', async ({
    page,
}) => {
    const before = await stockOf(page, 'E2E-TEE');

    await open(page, '/app/purchases/create');
    await expect(
        page.getByRole('heading', { name: 'New purchase' }),
    ).toBeVisible();
    // The vendor field is a type-ahead combobox: type, then Enter picks the highlighted match.
    const vendor = page.getByRole('combobox', { name: /^Vendor/ });
    await vendor.focus();
    await page.keyboard.type('E2E Textile');
    await page.keyboard.press('Enter');
    await expect(vendor).toHaveValue('E2E Textile Mills');

    // F2 focuses the scan field; each Enter of the same SKU adds one more unit.
    await page.keyboard.press('F2');
    await expect(page.locator('#scan')).toBeFocused();
    for (let i = 0; i < 3; i++) {
        await page.keyboard.type('E2E-TEE');
        await page.keyboard.press('Enter');
        await expect(page.locator('#scan')).toHaveValue('');
    }
    await expect(page.getByRole('rowheader', { name: /E2E Tee/ })).toBeVisible();
    await page.keyboard.press('Control+Enter');
    await expect(page).toHaveURL(/\/app\/purchases\/[0-9a-f-]{36}$/);

    const after = await stockOf(page, 'E2E-TEE');
    expect(after.onHand).toBe(before.onHand + 3);
    expect(after.purchases).toBe(before.purchases + 1);
});

test('typing in the scan field lists matching products to pick from the keyboard', async ({
    page,
}) => {
    await open(page, '/app/purchases/create');
    await page.keyboard.press('F2');
    await expect(page.locator('#scan')).toBeFocused();
    // No Enter yet: the matches appear as you type.
    await page.keyboard.type('E2E Cap');
    const matches = page.getByRole('list', { name: 'Matching products' });
    await expect(matches.getByText('E2E Cap')).toBeVisible();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await expect(page.getByRole('rowheader', { name: /E2E Cap/ })).toBeVisible();
    await expect(page.locator('#scan')).toHaveValue('');
});

test('the audit log and the customer statement keep the mockup layout', async ({
    page,
}) => {
    await open(page, '/app/settings/audit');
    await expect(
        page.getByRole('heading', { name: 'Immutable Audit Security Trail' }),
    ).toBeVisible();
    await expect(
        page.getByRole('columnheader', { name: 'Terminal / IP' }),
    ).toBeVisible();
    await expect(
        page.getByRole('combobox', { name: 'All Actions' }),
    ).toBeVisible();

    await open(page, '/app/customers/statement');
    await expect(
        page.getByRole('heading', { name: 'Customer Statement' }),
    ).toBeVisible();
    await expect(page.getByText('Choose a customer')).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Print statement' }),
    ).toBeDisabled();
});
