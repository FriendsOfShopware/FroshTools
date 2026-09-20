import { describe, expect, it, vi } from 'vitest';
import { overrideIfExists } from './override-if-exists';

describe('overrideIfExists', () => {
    it('overrides a registered component', () => {
        Shopware.Component.register('ft-override-probe', {
            template: '<div class="probe"></div>',
        });

        expect(
            overrideIfExists('ft-override-probe', {
                computed: {
                    marked() {
                        return true;
                    },
                },
            })
        ).toBe(true);
    });

    it('returns false when the component is missing', () => {
        const override = vi.spyOn(Shopware.Component, 'override');

        expect(
            overrideIfExists('ft-does-not-exist', {
                template: '<div></div>',
            })
        ).toBe(false);

        expect(override).not.toHaveBeenCalled();
        override.mockRestore();
    });
});
