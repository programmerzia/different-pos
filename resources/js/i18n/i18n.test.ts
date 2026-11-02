import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { describe, expect, it } from 'vite-plus/test';
import bn from './bn.json';
import en from './en.json';

const flatten = (obj: Record<string, unknown>, prefix = ''): string[] =>
    Object.entries(obj).flatMap(([k, v]) =>
        typeof v === 'object' && v !== null
            ? flatten(v as Record<string, unknown>, `${prefix}${k}.`)
            : [`${prefix}${k}`],
    );

const walk = (dir: string): string[] =>
    readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (['ui', 'routes', 'actions', 'wayfinder'].includes(name)) {
            return [];
        }

        return statSync(path).isDirectory()
            ? walk(path)
            : /\.tsx?$/.test(name) && !name.endsWith('.test.ts')
              ? [path]
              : [];
    });

describe('i18n resources', () => {
    it('EN and BN have exactly the same keys', () => {
        expect(flatten(bn).sort()).toEqual(flatten(en).sort());
    });

    it('has no empty strings', () => {
        const values = (o: Record<string, unknown>): unknown[] =>
            Object.values(o).flatMap((v) =>
                typeof v === 'object' && v
                    ? values(v as Record<string, unknown>)
                    : [v],
            );
        expect(
            values(en)
                .concat(values(bn))
                .filter((v) => v === ''),
        ).toEqual([]);
    });

    it('every literal t("…") key used in the app exists', () => {
        const known = new Set(flatten(en));
        const missing: string[] = [];
        for (const file of walk(resolve(__dirname, '..'))) {
            const source = readFileSync(file, 'utf8');
            for (const m of source.matchAll(
                /\bt\(\s*'([a-z_]+(?:\.[a-zA-Z_0-9]+)+)'/g,
            )) {
                if (!known.has(m[1] as string)) {
                    missing.push(`${file}: ${m[1]}`);
                }
            }
            for (const m of source.matchAll(
                /\b(?:labelKey|headerKey):\s*'([a-z_]+(?:\.[a-zA-Z_0-9]+)+)'/g,
            )) {
                if (!known.has(m[1] as string)) {
                    missing.push(`${file}: ${m[1]}`);
                }
            }
        }
        expect(missing).toEqual([]);
    });
});
