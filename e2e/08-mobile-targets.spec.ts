import { expect, test, type Locator, type Page } from '@playwright/test';
import { open, signIn } from './support';

// A phone (390×844, touch = coarse pointer): every target we touch is at least 44 px (03_UI_Spec).
test.use({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true });

const MIN = 44;

async function expectTarget(target: Locator, name: string) {
    await expect(target, name).toBeVisible();
    const box = await target.boundingBox();
    expect(box, name).not.toBeNull();
    expect(Math.round(box?.height ?? 0), `${name} height`).toBeGreaterThanOrEqual(MIN);
    expect(Math.round(box?.width ?? 0), `${name} width`).toBeGreaterThanOrEqual(MIN);
}

async function coarse(page: Page) {
    expect(await page.evaluate(() => matchMedia('(pointer: coarse)').matches)).toBe(true);
}

test('the login Remember-me control is a 44 px hit area', async ({ page }) => {
    await page.goto('/login');
    await coarse(page);
    await expectTarget(page.locator('label:has(#remember)'), 'remember me');
});

test('top bar, list buttons, customer names and dashboard links are 44 px on a phone', async ({
    page,
}) => {
    await signIn(page);
    await open(page, '/app/customers');
    await coarse(page);

    await expectTarget(page.getByRole('link', { name: 'Home' }).first(), 'home logo');
    await expectTarget(page.getByRole('button', { name: 'Columns' }), 'columns');
    await expectTarget(page.getByRole('button', { name: 'Print' }), 'print');
    const names = page.locator('[data-list-row] a[href^="/app/customers/"]');
    await expectTarget(names.first(), 'customer name link');

    // The search hint fits: the short placeholder on phones, the full one stays the label.
    const search = page.locator('#page-search');
    await expect(search).toHaveAttribute('placeholder', 'Name, phone, no.');
    await expect(search).toHaveAttribute('aria-label', 'Search name, shop, phone or number');

    await open(page, '/app');
    const links = page.locator('main a.touch-link');
    const count = await links.count();
    for (let i = 0; i < count; i++) {
        if (await links.nth(i).isVisible()) {
            const box = await links.nth(i).boundingBox();
            expect(Math.round(box?.height ?? 0), `dashboard link ${i}`).toBeGreaterThanOrEqual(MIN);
        }
    }
});
