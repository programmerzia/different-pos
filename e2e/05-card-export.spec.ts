import { expect, test } from '@playwright/test';
import { open, signIn } from './support';

test('exports the selected membership card as CSV with the keyboard', async ({
    page,
}) => {
    await signIn(page);
    await open(page, '/app/membership-cards');

    const row = page.getByRole('row', { name: /Rina Member/ });
    await expect(row).toBeVisible();
    await row.focus();
    await page.keyboard.press('Space');
    await expect(row).toHaveAttribute('data-selected', '');

    // The bulk toolbar sits above the table: Shift+Tab back from the row to the CSV button.
    const csv = page.getByRole('button', { name: /CSV/i });
    await expect(csv).toBeVisible();
    for (
        let i = 0;
        i < 12 && !(await csv.evaluate((el) => el === document.activeElement));
        i++
    ) {
        await page.keyboard.press('Shift+Tab');
    }
    await expect(csv).toBeFocused();

    const download = page.waitForEvent('download');
    await page.keyboard.press('Enter');
    const file = await download;
    expect(file.suggestedFilename()).toMatch(/\.csv$/);
    const stream = await file.createReadStream();
    let body = '';
    for await (const chunk of stream) {
        body += chunk.toString();
    }
    expect(body).toContain('Rina Member');
    // First column is the customer number, last the card code (MC + 8-digit number + 2-digit issue).
    expect(body).toContain('customer_no');
    const line =
        body.split(/\r?\n/).find((l) => l.includes('Rina Member')) ?? '';
    expect(line).toMatch(/^"?[\d ]{8,9}"?,"?Rina Member/);
    expect(line).toMatch(/MC\d{10}"?$/);
});
