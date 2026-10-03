import { describe, expect, it } from 'vitest';
import {
    buildShopwareComponent,
    loadShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

describe('sw-admin-menu override', () => {
    it('keeps the 6.7.15 title mt-text and swaps only the inner label', async () => {
        await loadShopwareComponent('sw-admin-menu');
        await import('./index');

        const component = await buildShopwareComponent('sw-admin-menu');

        expect(component).toBeTruthy();

        expect(component.computed.shopwareVersion.call({})).toBe(
            Shopware.Context.app.config.version
        );

        const template = String(component.template ?? component);
        const hasTrunkTitle =
            template.includes('sw_admin_menu_header_title') ||
            template.includes('sw-admin-menu__title') ||
            template.includes('frosh-tools-health-status');

        if (!hasTrunkTitle) {
            // Shopware 6.6 / 6.7.13 keep the badge on sw-version instead.
            return;
        }

        expect(template).toEqual(
            expect.stringMatching(/frosh-tools-health-status/)
        );
        expect(template).toEqual(expect.stringMatching(/sw-admin-menu__title/));
        expect(template).toEqual(expect.stringMatching(/textProjectName/));
        expect(template).toEqual(
            expect.stringMatching(
                /<\/frosh-tools-health-status>\s*<span v-if="shopwareVersion"> · {{ shopwareVersion }}<\/span>/
            )
        );
        expect(template).not.toEqual(
            expect.stringMatching(
                /<frosh-tools-health-status>\s*<mt-text[\s\S]*sw-admin-menu__title/
            )
        );
    });
});
