import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { expect, type Page } from '@playwright/test';

/** Presses Tab until the element has focus (links in between are part of the tab order). */
export async function tabTo(page: Page, selector: string, max = 8) {
    const target = page.locator(selector);
    for (let i = 0; i < max; i++) {
        await page.keyboard.press('Tab');
        if (await target.evaluate((el) => el === document.activeElement)) {
            break;
        }
    }
    await expect(target).toBeFocused();
}

export const OWNER = { login: 'owner@demo.test', password: 'password' };

/** Signs in with the keyboard only: Tab to each field, type, Enter. */
export async function login(page: Page) {
    await page.goto('/login');
    await expect(page.locator('#login')).toBeVisible();
    // The identifier field takes focus on load; Tab moves on to the password.
    await expect(page.locator('#login')).toBeFocused();
    await page.keyboard.type(OWNER.login);
    await tabTo(page, '#password');
    await page.keyboard.type(OWNER.password);
    await page.keyboard.press('Enter');
}

/** The branch picker focuses the last used branch; "1" chooses the first one. */
export async function chooseBranch(page: Page) {
    await expect(page).toHaveURL(/\/app\/branch/);
    await expect(
        page.getByRole('button', { name: /Main Branch/ }),
    ).toBeFocused();
    await page.keyboard.press('1');
    await expect(page).not.toHaveURL(/\/app\/branch/);
}

const AUTH = join(process.cwd(), 'e2e', '.auth.json');

/**
 * Signs in with the keyboard once (login throttling would stop a dozen logins in a minute) and keeps
 * the session cookie for the other tests. The picker only shows until a branch has been used once.
 */
export async function signIn(page: Page) {
    if (existsSync(AUTH)) {
        const state = JSON.parse(readFileSync(AUTH, 'utf8'));
        await page.context().addCookies(state.cookies);
        await page.goto('/app');
        await expect(page).toHaveURL(/\/app/);

        return;
    }
    await login(page);
    await expect(page).toHaveURL(/\/app/);
    if (page.url().includes('/app/branch')) {
        await chooseBranch(page);
    }
    await saveSession(page);
}

export async function saveSession(page: Page) {
    await page.context().storageState({ path: AUTH });
}

/** Opens a page by URL (typing the address is not a mouse action) and waits for the app shell. */
export async function open(page: Page, path: string) {
    await page.goto(path);
    if (path.startsWith('/app/pos')) {
        await expect(page.getByLabel(/Scan barcode, SKU/)).toBeVisible();
    } else {
        await expect(page.locator('h1').first()).toBeVisible();
    }
}

/** Numbers handed from one spec to the next (specs run in file order on one worker). */
const STATE = join(process.cwd(), 'e2e', '.state.json');

export function remember(values: Record<string, string>) {
    const old = existsSync(STATE)
        ? JSON.parse(readFileSync(STATE, 'utf8'))
        : {};
    writeFileSync(STATE, JSON.stringify({ ...old, ...values }));
}

export function recall(key: string): string {
    const value = JSON.parse(readFileSync(STATE, 'utf8'))[key];
    if (typeof value !== 'string') {
        throw new Error(
            `Nothing remembered for "${key}": run the specs in order.`,
        );
    }

    return value;
}

/** Scan a SKU, attach a customer by phone (F4), open pay (F9). Leaves the payment dialog open. */
export async function ringUp(
    page: Page,
    sku: string,
    customerPhone: string,
    customerName: string,
) {
    await open(page, '/app/pos');
    await page.keyboard.press('F2');
    const scan = page.getByLabel(/Scan barcode, SKU/);
    await expect(scan).toBeFocused();
    // The register selects the field's text on the next frame; wait for it before typing.
    await page.evaluate(
        () =>
            new Promise((done) =>
                requestAnimationFrame(() => requestAnimationFrame(done)),
            ),
    );
    await page.keyboard.type(sku);
    await page.keyboard.press('Enter');
    await expect(page.getByText(sku).first()).toBeVisible();

    await page.keyboard.press('F4');
    const search = page.getByLabel('Customer no., phone, name or shop');
    await expect(search).toBeFocused();
    await page.keyboard.type(customerPhone);
    const hit = page
        .getByRole('list', { name: 'Customers' })
        .getByRole('button', { name: new RegExp(customerName) });
    await expect(hit).toBeVisible();
    await page.keyboard.press('ArrowDown');
    await page.keyboard.press('Enter');
    await expect(page.getByRole('dialog')).toBeHidden();
    await expect(page.getByText(customerName).first()).toBeVisible();

    await page.keyboard.press('F9');
    await expect(page.getByRole('dialog', { name: /./ })).toBeVisible();
}
