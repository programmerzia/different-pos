import { describe, expect, it } from 'vite-plus/test';
import { makeCan } from '@/hooks/use-can';
import { ActionRegistry } from './actions';
import { pushRecent, readRecents } from './recents';

const mem = () => {
    const data = new Map<string, string>();

    return {
        getItem: (k: string) => data.get(k) ?? null,
        setItem: (k: string, v: string) => void data.set(k, v),
    };
};

describe('action registry', () => {
    const registry = new ActionRegistry();
    registry.register(
        { id: 'row.open', entity: '*', labelKey: 'a', requires: 'href' },
        {
            id: 'row.delete',
            entity: '*',
            labelKey: 'd',
            group: 'danger',
            requires: 'handler',
        },
        {
            id: 'sale.void',
            entity: 'sale',
            labelKey: 'v',
            group: 'danger',
            permission: 'sales.void',
        },
        {
            id: 'sale.return',
            entity: 'sale',
            labelKey: 'r',
            permission: 'returns.create',
        },
        {
            id: 'sale.same_day',
            entity: 'sale',
            labelKey: 's',
            visible: (row: { today: boolean }) => row.today,
        },
        {
            id: 'cmd.new_sale',
            entity: null,
            labelKey: 'n',
            permission: 'pos.sell',
        },
    );

    it('hides (never disables) what the user may not do', () => {
        const ids = (perms: string[]) =>
            registry
                .forEntity('sale', makeCan(perms), { today: true })
                .map((a) => a.id);
        expect(ids([])).toEqual(['sale.same_day']);
        expect(ids(['returns.create', 'sales.void'])).toEqual([
            'sale.return',
            'sale.same_day',
            'sale.void',
        ]);
    });

    it('generic actions need a surface that can back them', () => {
        const can = makeCan([]);
        expect(registry.forEntity('x', can, {}, {}).map((a) => a.id)).toEqual(
            [],
        );
        expect(
            registry
                .forEntity(
                    'x',
                    can,
                    {},
                    {
                        href: '/p/1',
                        handlers: { 'row.delete': () => undefined },
                    },
                )
                .map((a) => a.id),
        ).toEqual(['row.open', 'row.delete']);
    });

    it('per-row visibility and danger ordering', () => {
        const can = makeCan(['sales.void']);
        const list = registry
            .forEntity('sale', can, { today: false })
            .map((a) => a.id);
        expect(list).toEqual(['sale.void']);
    });

    it('global commands are permission filtered and separate from row actions', () => {
        expect(registry.commands(makeCan([])).map((a) => a.id)).toEqual([]);
        expect(
            registry.commands(makeCan(['pos.sell'])).map((a) => a.id),
        ).toEqual(['cmd.new_sale']);
    });
});

describe('makeCan', () => {
    it('any-of semantics; no requirement means allowed', () => {
        const can = makeCan(['a']);
        expect(can()).toBe(true);
        expect(can(['b', 'a'])).toBe(true);
        expect(can('b')).toBe(false);
    });
});

describe('palette recents', () => {
    it('most recent first, de-duplicated, capped at 6', () => {
        const storage = mem();
        for (let i = 0; i < 8; i++) {
            pushRecent(
                { id: `p${i}`, label: `P${i}`, href: `/p/${i}` },
                storage,
            );
        }
        pushRecent({ id: 'p5', label: 'P5', href: '/p/5' }, storage);
        const recents = readRecents(storage);
        expect(recents).toHaveLength(6);
        expect(recents[0]?.id).toBe('p5');
        expect(recents.filter((r) => r.id === 'p5')).toHaveLength(1);
    });

    it('ignores corrupt storage', () => {
        expect(readRecents({ getItem: () => '{oops' })).toEqual([]);
    });
});
