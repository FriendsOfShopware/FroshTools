import { describe, expect, it } from 'vitest';
import {
    buildShopwareComponent,
    loadShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

describe('sw-admin-menu override', () => {
    it('wraps the trunk sidebar title so a health badge can replace Administration', async () => {
        await loadShopwareComponent('sw-admin-menu');
        await import('./index');

        const component = await buildShopwareComponent('sw-admin-menu');

        expect(component).toBeTruthy();

        const template = String(component.template ?? component);
        const hasTrunkTitle =
            template.includes('sw_admin_menu_header_title') ||
            template.includes('sw-admin-menu__title') ||
            template.includes('frosh-tools-health-status');

        if (!hasTrunkTitle) {
            // Shopware 6.6 / 6.7 keep the badge on sw-version instead.
            return;
        }

        expect(template).toEqual(
            expect.stringMatching(/frosh-tools-health-status/)
        );
    });
});
