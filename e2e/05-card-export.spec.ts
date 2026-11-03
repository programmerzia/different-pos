import { expect, test } from '@playwright/test';
import { open, signIn } from './support';

test('queues a member in the card studio and prints and exports with the keyboard', async ({
    page,
}) => {
    await signIn(page);
    await open(page, '/app/membership-cards');
    await expect(
        page.getByRole('heading', { name: 'Membership Card Studio & Production' }),
    ).toBeVisible();

    // "/" opens Select Member with the search focused; the search runs on the server.
    await page.keyboard.press('/');
    const search = page.getByLabel('Search name, phone, number or shop');
    await expect(search).toBeFocused();
    await page.keyboard.type('Rina');
    await expect(page.locator('#member-list li')).toHaveCount(1);
    const rina = page.locator('#member-list').getByRole('button', { name: /^Rina Member \(/ });
    await expect(rina).toBeVisible();

    // Down walks into the list, Space queues, Enter shows the card.
    await page.keyboard.press('ArrowDown');
    await expect(rina).toBeFocused();
    await page.keyboard.press('Space');
    await expect(page.getByRole('button', { name: 'Queue Rina Member for printing' })).toHaveAttribute('aria-pressed', 'true');
    await page.keyboard.press('Enter');
    await expect(page.locator('#member-list')).toBeHidden();
    await expect(page.getByTestId('card-number')).toBeVisible();

    const queue = page.getByRole('table', { name: /Bulk Print Queue/ });
    await expect(queue.getByRole('row', { name: /Rina Member/ })).toBeVisible();
    await expect(page.getByText('1 Cards Queued')).toBeVisible();

    // P = Print Batch: opens the print-ready page for the queued members.
    const printed = page.context().waitForEvent('response', (r) => r.url().includes('/app/membership-cards/print?'));
    await page.keyboard.press('p');
    const response = await printed;
    expect(response.status()).toBe(200);
    expect(response.url()).toContain('layout=cr80');
    expect(await response.text()).toContain('Rina Member');

    // Shift+E = the press data CSV for the queue.
    const download = page.waitForEvent('download');
    await page.keyboard.press('Shift+E');
    const file = await download;
    expect(file.suggestedFilename()).toMatch(/\.csv$/);
    const stream = await file.createReadStream();
    let body = '';
    for await (const chunk of stream) {
        body += chunk.toString();
    }
    expect(body).toContain('customer_no');
    const line = body.split(/\r?\n/).find((l) => l.includes('Rina Member')) ?? '';
    expect(line).toMatch(/^"?[\d ]{8,9}"?,"?Rina Member/);
    expect(line).toMatch(/MC\d{10}"?$/);
});
