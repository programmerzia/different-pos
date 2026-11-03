import { describe, expect, it } from 'vite-plus/test';
import { localStamp } from '@/components/sales/date-time';

describe('localStamp', () => {
    it('keeps the tenant wall time of an ISO stamp with offset (no browser-zone conversion)', () => {
        expect(localStamp('2026-11-03T18:26:27+06:00')).toEqual({
            date: '2026-11-03',
            time: '18:26',
        });
    });

    it('reads the server "Y-m-d H:i" form and a bare date', () => {
        expect(localStamp('2026-11-03 13:56')).toEqual({
            date: '2026-11-03',
            time: '13:56',
        });
        expect(localStamp('2026-11-03')).toEqual({
            date: '2026-11-03',
            time: '',
        });
    });

    it('is empty for null', () => {
        expect(localStamp(null)).toEqual({ date: '', time: '' });
    });
});
