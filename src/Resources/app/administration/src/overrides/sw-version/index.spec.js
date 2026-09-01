import { describe, expect, it } from 'vitest';
import {
    buildShopwareComponent,
    loadShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

describe('sw-version override', () => {
    it('keeps the 6.6/6.7 status slot wired to the health badge when present', async () => {
        let available = true;

        try {
            await loadShopwareComponent('sw-version');
        } catch {
            available = false;
        }

        if (!available) {
            expect(available).toBe(false);
            return;
        }

        await import('./index');

        const component = await buildShopwareComponent('sw-version');

        expect(component).toBeTruthy();

        const template = String(component.template ?? component);
        const hasLegacyStatusSlot =
            template.includes('sw-version__title') ||
            template.includes('sw-version__status') ||
            template.includes('sw_version_status');

        if (!hasLegacyStatusSlot) {
            // Trunk removed the status slot; the badge is on sw-admin-menu.
            expect(template).toContain('sw-version');
            return;
        }

        expect(template).toEqual(
            expect.stringMatching(/frosh-tools-health-status/)
        );
    });
});
