import { describe, expect, it } from 'vitest';
import {
    insertVariable,
    renderPreview,
    smsCost,
    smsSegments,
} from './sms-text';

describe('smsSegments', () => {
    it('counts nothing for an empty text', () => {
        expect(smsSegments('')).toMatchObject({
            segments: 0,
            units: 0,
            encoding: 'gsm7',
        });
    });

    it('fits 160 GSM-7 characters in one part and splits at 153 after that', () => {
        expect(smsSegments('a'.repeat(160))).toMatchObject({
            segments: 1,
            perSegment: 160,
        });
        expect(smsSegments('a'.repeat(161))).toMatchObject({
            segments: 2,
            perSegment: 153,
        });
        expect(smsSegments('a'.repeat(306)).segments).toBe(2);
        expect(smsSegments('a'.repeat(307)).segments).toBe(3);
    });

    it('counts GSM extension characters twice', () => {
        expect(smsSegments('{}').units).toBe(4);
        expect(smsSegments('€'.repeat(80)).segments).toBe(1);
        expect(smsSegments('€'.repeat(81)).segments).toBe(2);
    });

    it('switches to UCS-2 (70/67) for Bangla or the taka sign', () => {
        expect(smsSegments('ধন্যবাদ')).toMatchObject({
            encoding: 'ucs2',
            segments: 1,
        });
        expect(smsSegments('৳'.repeat(70)).segments).toBe(1);
        expect(smsSegments('৳'.repeat(71))).toMatchObject({
            segments: 2,
            perSegment: 67,
        });
    });
});

describe('smsCost', () => {
    it('multiplies parts, recipients and the per-part price in paisa', () => {
        expect(smsCost(2, 14, 35)).toBe(980);
        expect(smsCost(0, 14, 35)).toBe(0);
        expect(smsCost(1, -1, 35)).toBe(0);
    });
});

describe('insertVariable', () => {
    it('appends with a separating space', () => {
        expect(insertVariable('Dear', 'name')).toEqual({
            text: 'Dear {name}',
            caret: 11,
        });
    });

    it('inserts at the caret without a double space and replaces a selection', () => {
        expect(insertVariable('Hi , bye', 'name', 3)).toEqual({
            text: 'Hi {name}, bye',
            caret: 9,
        });
        expect(insertVariable('Hi XX', 'due', 3, 5)).toEqual({
            text: 'Hi {due}',
            caret: 8,
        });
        expect(insertVariable('', 'shop')).toEqual({
            text: '{shop}',
            caret: 6,
        });
    });

    it('clamps a caret outside the text', () => {
        expect(insertVariable('ab', 'number', 99)).toEqual({
            text: 'ab {number}',
            caret: 11,
        });
    });
});

describe('renderPreview', () => {
    it('fills known placeholders and keeps unknown ones', () => {
        expect(
            renderPreview('{name} owes {due} at {shop} {oops}', {
                name: 'Rahim',
                due: '1,200.00',
                shop: 'DF',
            }),
        ).toBe('Rahim owes 1,200.00 at DF {oops}');
    });
});
