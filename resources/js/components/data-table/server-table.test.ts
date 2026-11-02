import { describe, expect, it } from 'vite-plus/test';
import {
    buildTableQuery,
    DEFAULT_TABLE_STATE,
    nextSort,
    parseTableQuery,
} from './server-table';

describe('server table query', () => {
    it('round-trips page, sort, search and filters', () => {
        const state = parseTableQuery(
            '?page=3&per_page=50&sort=-name&q=shirt&filter[status]=paid&filter[empty]=',
        );
        expect(state).toEqual({
            page: 3,
            perPage: 50,
            sort: '-name',
            q: 'shirt',
            filters: { status: 'paid' },
        });
        expect(buildTableQuery(state)).toEqual({
            page: 3,
            per_page: 50,
            sort: '-name',
            q: 'shirt',
            'filter[status]': 'paid',
        });
    });

    it('omits defaults so URLs stay short', () => {
        expect(buildTableQuery(DEFAULT_TABLE_STATE)).toEqual({});
        expect(parseTableQuery('')).toEqual(DEFAULT_TABLE_STATE);
    });

    it('survives garbage values', () => {
        expect(parseTableQuery('?page=abc&per_page=-4').page).toBe(1);
        expect(parseTableQuery('?page=abc&per_page=-4').perPage).toBe(25);
    });

    it('sort cycles asc → desc → default', () => {
        expect(nextSort(null, 'name')).toBe('name');
        expect(nextSort('name', 'name')).toBe('-name');
        expect(nextSort('-name', 'name')).toBeNull();
        expect(nextSort('-total', 'name')).toBe('name');
    });
});
