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
    // The first row is "All members"; then the matching members.
    await expect(page.locator('#member-list [data-member]:not([data-member="-1"])')).toHaveCount(1);
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
    expect(response.url()).toMatch(/[?&]ids=\d+/);
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

    // The number is masked on screen (last four digits visible); R reveals it.
    const number = page.getByTestId('card-number');
    await page.mouse.move(0, 0);
    await page.keyboard.press('Escape');
    await expect(number.locator('.mc-num-masked')).toBeVisible();
    await expect(number.locator('.mc-num-masked')).toHaveText(/^•••• \d{4}$/);
    await expect(number.locator('.mc-num-full')).toBeHidden();
    await page.keyboard.press('r');
    await expect(number.locator('.mc-num-full')).toBeVisible();
    await expect(number.locator('.mc-num-masked')).toBeHidden();

    // "All members" selects every listed member: the preview becomes a gallery, the queue follows.
    await page.keyboard.press('/');
    await page.keyboard.press('Control+a');
    await page.keyboard.press('Backspace');
    await page.getByRole('button', { name: /^All members/ }).click();
    await page.keyboard.press('Escape');
    const gallery = page.getByTestId('card-gallery');
    await expect(gallery).toBeVisible();
    const count = await gallery.getByTestId('membership-card').count();
    expect(count).toBeGreaterThan(1);
    await expect(queue.locator('tbody tr')).toHaveCount(count);
});
