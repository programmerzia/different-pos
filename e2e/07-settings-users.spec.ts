import { expect, test } from '@playwright/test';
import { open, signIn } from './support';

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('adds a staff member with a PIN in the dialog and resets the PIN, all on the Users & Roles page', async ({
    page,
}) => {
    await open(page, '/app/settings/users');
    await expect(
        page.getByRole('heading', { name: 'Staff Users & Role Permissions' }),
    ).toBeVisible();
    await expect(
        page.getByRole('heading', { name: 'Permissions matrix' }),
    ).toBeVisible();

    // N opens the Add Staff dialog with the name focused; Ctrl+Enter saves.
    await page.locator('h1').first().click();
    await page.keyboard.press('n');
    const dialog = page.getByRole('dialog', { name: /Add Staff Member/ });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByLabel(/^Full name/)).toBeFocused();
    await page.keyboard.type('E2E Floor Staff');
    await dialog.getByLabel(/^Username/).fill('e2efloor');
    await dialog.getByLabel(/^Password/).fill('a-strong-pass-1');
    await dialog.getByLabel(/^Initial register PIN/).fill('2468');
    await dialog.getByRole('checkbox', { name: 'Cashier' }).focus();
    await page.keyboard.press('Space');
    await dialog.getByRole('checkbox', { name: /^Main Branch/ }).focus();
    await page.keyboard.press('Space');
    await page.keyboard.press('Control+Enter');
    await expect(dialog).toBeHidden();

    const row = page.getByRole('row', { name: /E2E Floor Staff/ });
    await expect(row).toBeVisible();
    await expect(row.getByText('Cashier')).toBeVisible();

    // Reset PIN: a short PIN is refused in place, a 6-digit one is saved.
    await row
        .getByRole('button', { name: 'Reset PIN: E2E Floor Staff' })
        .focus();
    await page.keyboard.press('Enter');
    const pin = page.getByRole('dialog', {
        name: 'Reset PIN: E2E Floor Staff',
    });
    await expect(pin).toBeVisible();
    await page.keyboard.type('12');
    await page.keyboard.press('Enter');
    await expect(pin.getByRole('alert')).toHaveText(
        'The PIN must be 4 to 6 digits.',
    );
    await page.keyboard.type('3579');
    await page.keyboard.press('Enter');
    await expect(pin).toBeHidden();
    await expect(
        page.getByText('PIN updated for E2E Floor Staff.'),
    ).toBeVisible();
});

test('the roles deep link renders the same page focused on the matrix', async ({
    page,
}) => {
    await open(page, '/app/settings/roles');
    await expect(
        page.getByRole('heading', { name: 'Staff Users & Role Permissions' }),
    ).toBeVisible();
    await expect(page.locator('#roles')).toBeFocused();
    await expect(
        page.getByRole('checkbox', { name: 'Owner: Sell at the register' }),
    ).toBeDisabled();
});

test('the settings hub saves with Ctrl+S and its menu jumps between sections', async ({
    page,
}) => {
    await open(page, '/app/settings/business');
    await expect(
        page.getByRole('heading', { name: 'Store & System Settings' }),
    ).toBeVisible();
    const menu = page.getByRole('navigation', { name: 'Configuration menu' });
    await expect(
        menu.getByRole('link', { name: /Business Profile/ }),
    ).toHaveAttribute('aria-current', 'page');
    await expect(
        menu.getByRole('button', { name: /Receipt & Printers/ }),
    ).toHaveAttribute('aria-disabled', 'true');

    await page.getByLabel('Legal name').fill('E2E Legal Name Ltd');
    await expect(
        page.getByText('You have unsaved changes in settings.'),
    ).toBeVisible();
    await page.keyboard.press('Control+s');
    await expect(
        page.getByText('You have unsaved changes in settings.'),
    ).toBeHidden();
    await page.reload();
    await expect(page.getByLabel('Legal name')).toHaveValue(
        'E2E Legal Name Ltd',
    );

    await menu.getByRole('link', { name: /Customer PIN Policy/ }).click();
    await expect(page).toHaveURL(/#pin-policy$/);
    await expect(
        menu.getByRole('link', { name: /Customer PIN Policy/ }),
    ).toHaveAttribute('aria-current', 'page');

    await menu.getByRole('link', { name: /^Branches/ }).click();
    await expect(page).toHaveURL(/\/app\/settings\/branches$/);
    await expect(
        page.getByRole('heading', { name: 'Branches', exact: true }),
    ).toBeVisible();
});

test('the messaging composer counts SMS parts and shows the phone preview', async ({
    page,
}) => {
    await open(page, '/app/messages');
    await expect(
        page.getByRole('heading', { name: 'Customer Messaging' }),
    ).toBeVisible();
    const body = page.getByLabel('Message text');
    await body.fill('Hello');
    await page.getByRole('button', { name: 'Insert {name}' }).click();
    await expect(body).toHaveValue('Hello {name}');
    await expect(
        page.getByText(/12 chars · 1 SMS part\(s\) · GSM/),
    ).toBeVisible();
    await expect(page.getByText('Hello Rahim Uddin')).toBeVisible();

    await body.fill('ধন্যবাদ');
    await expect(page.getByText(/SMS part\(s\) · Unicode/)).toBeVisible();

    await page.getByRole('button', { name: 'Message Log' }).click();
    await expect(page.getByPlaceholder('Search phone or text')).toBeVisible();
});
