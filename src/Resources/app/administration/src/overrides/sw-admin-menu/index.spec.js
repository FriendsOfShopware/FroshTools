import { describe, expect, it } from 'vitest';
import {
    buildShopwareComponent,
    loadShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

describe('sw-admin-menu override', () => {
    it('fills the 6.7.15 title status slot with an inline health dot', async () => {
        await loadShopwareComponent('sw-admin-menu');
        await import('./index');

        const component = await buildShopwareComponent('sw-admin-menu');

        expect(component).toBeTruthy();

        const template = String(component.template ?? component);
        const hasTitleStatusSlot =
            template.includes('sw_admin_menu_header_title_status') ||
            template.includes('sw-admin-menu__title');

        if (!hasTitleStatusSlot) {
            // Shopware 6.6 / 6.7.13 keep the badge on sw-version instead.
            return;
        }

        expect(template).toEqual(
            expect.stringMatching(/frosh-tools-health-status/)
        );
        expect(template).toEqual(expect.stringMatching(/presentation="dot"/));
        expect(template).not.toEqual(
            expect.stringMatching(
                /<frosh-tools-health-status>\s*<mt-text[\s\S]*sw-admin-menu__title/
            )
        );
    });
});
