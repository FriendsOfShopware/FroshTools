import { describe, expect, it } from 'vitest';
import { PRIVILEGE } from './privileges';

describe('frosh-tools privileges', () => {
    it('keeps read and update privileges on separate keys', () => {
        const reads = Object.entries(PRIVILEGE)
            .filter(([key]) => key.endsWith('_READ') || key === 'READ')
            .map(([, value]) => value);
        const updates = Object.entries(PRIVILEGE)
            .filter(([key]) => key.endsWith('_UPDATE'))
            .map(([, value]) => value);

        expect(new Set(reads).size).toBe(reads.length);
        expect(new Set(updates).size).toBe(updates.length);
        expect(reads.some((privilege) => updates.includes(privilege))).toBe(
            false
        );
        expect(
            updates.every((privilege) => privilege.endsWith(':update'))
        ).toBe(true);
    });
});
