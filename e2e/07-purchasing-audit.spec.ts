import { expect, test } from '@playwright/test';
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
