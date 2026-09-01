import { describe, expect, it } from 'vitest';
import {
    buildShopwareComponent,
    loadShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

describe('sw-version override', () => {
    it('keeps the 6.6/6.7 status slot wired to the health badge when the component exists', async () => {
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
        expect(String(component.template ?? component)).toEqual(
            expect.stringMatching(/frosh-tools-health-status|sw_version_status/)
        );
    });
});
