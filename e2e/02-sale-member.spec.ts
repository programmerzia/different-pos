import { expect, test } from '@playwright/test';
import { open, remember, ringUp, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('register: bill line stepper, payment panel keys, Esc after a dialog', async ({
    page,
}) => {
    await ringUp(page, 'E2E-TEE', '01711000001', 'Rina Member');
    const pay = page.getByRole('dialog', { name: 'Payment & Due Settlement' });
    await expect(pay.getByText('Tender Breakdown')).toBeVisible();
    // The cash field takes the focus with the exact amount; Alt+D leaves it all as due.
    const cash = pay.getByRole('textbox', { name: 'Cash', exact: true });
    await expect(cash).toBeFocused();
    await page.keyboard.press('Alt+D');
    await expect(pay.getByText('Due: ৳450.00')).toBeVisible();
    await expect(
        pay.getByRole('button', { name: /Confirm Partial \/ Due Sale/ }),
    ).toBeVisible();
    await page.keyboard.press('Alt+E');
    await expect(pay.getByText('Change', { exact: true })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(pay).toBeHidden();
    // Esc that closed the dialog does not also ask to clear the bill.
    await expect(page.getByText('Clear the bill?')).toHaveCount(0);

    const bill = page.getByRole('table', { name: 'Bill lines' });
    await bill.getByRole('button', { name: 'Increase quantity' }).click();
    await expect(
        bill.getByRole('button', { name: 'Quantity of E2E Tee' }),
    ).toHaveText('2');
    await expect(
        page.getByRole('button', { name: /Pay ৳900\.00/ }),
    ).toBeVisible();
    await bill.getByRole('button', { name: 'Remove E2E Tee' }).click();
    await expect(page.getByText('Cart is empty')).toBeVisible();
});

test('register: the cart is the main view; the search dropdown and the browse surface add to it', async ({
    page,
}) => {
    await open(page, '/app/pos');
    await expect(page.getByText('Cart is empty')).toBeVisible();
    const catalogue = page.getByRole('region', { name: 'Catalogue' });
    await expect(catalogue).toHaveCount(0);

    // Typing shows the hits in a dropdown under the field; Enter adds the highlighted one.
    await page.keyboard.press('F2');
    await expect(page.getByLabel(/Scan barcode, SKU/)).toBeFocused();
    await page.evaluate(
        () =>
            new Promise((done) =>
                requestAnimationFrame(() => requestAnimationFrame(done)),
            ),
    );
    await page.keyboard.type('E2E Ca');
    const hits = page.getByRole('list', { name: 'Search results' });
    await expect(hits.getByRole('button', { name: /E2E Cap/ })).toBeVisible();
    await page.keyboard.press('Enter');
    const bill = page.getByRole('table', { name: 'Bill lines' });
    await expect(bill.getByText('E2E Cap')).toBeVisible();
    await expect(hits).toHaveCount(0);

    // Alt+G opens the browse surface over the bill; Esc goes back to the cart.
    await page.keyboard.press('Alt+G');
    await expect(catalogue).toBeVisible();
    await expect(
        catalogue.getByRole('button', { name: /E2E Bag/ }),
    ).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(catalogue).toBeHidden();
    await expect(bill.getByText('E2E Cap')).toBeVisible();

    // Typed text filters the open grid; ↓ enters it, Enter adds and returns to the cart.
    await page.keyboard.press('Alt+G');
    await expect(catalogue).toBeVisible();
    await page.keyboard.type('E2E Bag');
    await expect(
        catalogue.getByRole('button', { name: /E2E Bag/ }),
    ).toBeVisible();
    await expect(
        catalogue.getByRole('button', { name: /E2E Cap/ }),
    ).toHaveCount(0);
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await expect(catalogue).toBeHidden();
    await expect(bill.getByText('E2E Bag')).toBeVisible();
    await expect(
        page.getByRole('button', { name: /Pay ৳1,500\.00/ }),
    ).toBeVisible();

    // Del with the empty field removes the selected line, until the cart is empty again.
    await page.keyboard.press('F2');
    await page.keyboard.press('Delete');
    await page.keyboard.press('Delete');
    await expect(page.getByText('Cart is empty')).toBeVisible();
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
    const row = page.getByRole('row', { name: new RegExp(number) });
    await expect(row).toBeVisible();

    // Enter opens the sale as the invoice dialog over the list (deep link /app/sales/{id}).
    await row.focus();
    await page.keyboard.press('Enter');
    const invoice = page.getByRole('dialog');
    await expect(invoice).toContainText(number);
    await expect(
        invoice.getByRole('heading', { name: 'Purchased items & discounts' }),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/app\/sales\/\d+/);
    await page.keyboard.press('Escape');
    await expect(invoice).toBeHidden();
    await expect(page).toHaveURL(/\/app\/sales(\?|$)/);
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
