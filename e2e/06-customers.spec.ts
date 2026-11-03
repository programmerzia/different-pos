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
