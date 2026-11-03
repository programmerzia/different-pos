import { expect, test } from '@playwright/test';
import { open, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('adds a customer in the dialog with the keyboard and edits them from the profile', async ({
    page,
}) => {
    await open(page, '/app/customers');
    await expect(
        page.getByRole('heading', { name: 'Customers & Members' }),
    ).toBeVisible();

    // N opens the add dialog with the name focused; Ctrl+Enter saves and opens the profile.
    await page.locator('h1').first().click();
    await page.keyboard.press('n');
    const dialog = page.getByRole('dialog', {
        name: /New Customer Registration/,
    });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel(/^Full Name \(English\)/)).toBeFocused();
    await page.keyboard.type('Dialog Buyer');
    await dialog.getByLabel(/^Shop name/).fill('Dialog Traders');
    await dialog.getByLabel(/^Primary Mobile Number/).fill('01755-000111');
    await page.keyboard.press('Control+Enter');

    await expect(
        page.getByRole('heading', { name: 'Dialog Buyer' }),
    ).toBeVisible();
    await expect(page.getByText('Lifetime Spend (LTV)')).toBeVisible();
    await expect(page.getByRole('tab', { name: 'Overview' })).toHaveAttribute(
        'aria-selected',
        'true',
    );

    // E opens the edit dialog; Esc with a change asks first, then discards.
    await page.locator('h1').first().click();
    await page.keyboard.press('e');
    const edit = page.getByRole('dialog', { name: /Edit Customer/ });
    await expect(edit).toBeVisible();
    await expect(edit.getByLabel(/^Primary Mobile Number/)).toHaveValue(
        '01755-000111',
    );
    await edit.getByLabel(/^Full Name \(English\)/).fill('Dialog Buyer Two');
    await page.keyboard.press('Escape');
    await expect(page.getByText('Discard your changes?')).toBeVisible();
    await page.getByRole('button', { name: 'Discard changes' }).click();
    await expect(edit).toBeHidden();
    await expect(
        page.getByRole('heading', { name: 'Dialog Buyer' }),
    ).toBeVisible();
});

test('the add-customer deep link opens the dialog over the list and a duplicate phone is flagged', async ({
    page,
}) => {
    await open(page, '/app/customers/create');
    const dialog = page.getByRole('dialog', {
        name: /New Customer Registration/,
    });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel(/^Full Name \(English\)/).fill('Copy Cat');
    await dialog.getByLabel(/^Primary Mobile Number/).fill('01755000111');
    await dialog.getByRole('button', { name: 'Save customer' }).click();
    await expect(
        dialog.getByText(/already belongs to Dialog Buyer/).first(),
    ).toBeVisible();
    await expect(dialog).toBeVisible();
});

test('the list shows the mockup columns, totals and exports CSV', async ({
    page,
}) => {
    await open(page, '/app/customers');
    const table = page.getByRole('table', { name: 'Customers & Members' });
    for (const header of [
        'Customer #',
        'Name (EN / BN)',
        'Mobile Phone',
        'Tier / Group',
        'Balance / Due',
        'Credit Limit',
        'Card Status',
        'PIN Status',
    ]) {
        await expect(
            table.getByRole('columnheader', {
                name: new RegExp(header.replace(/[()/]/g, '\\$&')),
            }),
        ).toBeVisible();
    }
    await expect(table.getByText(/^Total \(/)).toBeVisible();

    const download = page.waitForEvent('download');
    await page.locator('h1').first().click();
    await page.keyboard.press('Shift+E');
    const file = await download;
    expect(file.suggestedFilename()).toMatch(/^customers-.*\.csv$/);
});

test('reaches the customer rows from the keyboard: search, ↓, J/K, Shift+F10 and Enter', async ({
    page,
}) => {
    await open(page, '/app/customers');

    // J on a fresh page enters the list on its first row.
    await page.locator('h1').first().click();
    await page.keyboard.press('j');
    const rows = page.locator('[data-list-row]');
    await expect(rows.first()).toBeFocused();

    // "/" focuses the search; after typing, ↓ moves to the first matching row.
    await page.keyboard.press('/');
    await expect(page.locator('#page-search')).toBeFocused();
    await page.keyboard.type('Rina');
    await expect(rows).toHaveCount(1);
    await page.keyboard.press('ArrowDown');
    await expect(rows.first()).toBeFocused();

    // Shift+F10 opens the row's context menu; Esc closes it and Enter opens the profile.
    await page.keyboard.press('Shift+F10');
    await expect(page.getByRole('menu')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('menu')).toBeHidden();
    await expect(rows.first()).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(
        page.getByRole('heading', { name: 'Rina Member' }),
    ).toBeVisible();

    // Alt+↓ jumps from the search field straight to the rows.
    await open(page, '/app/customers');
    await page.keyboard.press('/');
    await page.keyboard.press('Alt+ArrowDown');
    await expect(rows.first()).toBeFocused();
});
