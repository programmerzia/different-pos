import { describe, expect, it } from 'vite-plus/test';
import { returnValue, salesUrl } from './sales';

describe('returnValue', () => {
    it('prorates the payable value by quantity', () => {
        expect(returnValue(300_000, 1000, 3000)).toBe(100_000);
        expect(returnValue(100_000, 1000, 3000)).toBe(33_333);
        expect(returnValue(100_000, 0, 3000)).toBe(0);
        expect(returnValue(100_000, 1000, 0)).toBe(0);
    });
});

describe('salesUrl', () => {
    it('builds receipt and wizard urls', () => {
        expect(salesUrl.receipt(5, 'a4')).toBe('/app/sales/5/receipt?paper=a4');
        expect(salesUrl.returnCreate(5)).toBe('/app/returns/create?sale=5');
        expect(salesUrl.exchangeCreate()).toBe('/app/exchanges/create');
    });
});
