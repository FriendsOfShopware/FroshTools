import { describe, expect, it } from 'vitest';
import {
    buildShopwareComponent,
    loadShopwareComponent,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

describe('sw-data-grid-inline-edit override', () => {
    it('extends the core inline editor with date and datetime fields', async () => {
        await loadShopwareComponent('sw-data-grid-inline-edit');
        await import('./index');

        const component = await buildShopwareComponent(
            'sw-data-grid-inline-edit'
        );

        expect(component).toBeTruthy();
        expect(String(component.template ?? component)).toEqual(
            expect.stringMatching(
                /inlineEdit === 'date'|inline-edit === 'date'/i
            )
        );
    });
});
