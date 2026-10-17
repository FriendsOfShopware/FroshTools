import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import '../../../../mixin/sortable-table.js';
import '../../../../mixin/settings-result.js';
import './index.js';
import enGB from '../../snippet/en-GB.json';
import deDE from '../../snippet/de-DE.json';

async function createWrapper(service) {
    return mountRegistered('frosh-tools-tab-data-integrity', {
        provide: {
            froshToolsService: service,
        },
    });
}

describe('frosh-tools-tab-data-integrity', () => {
    it('loads the data integrity status on creation', async () => {
        const status = [{ id: 'duplicate-delivery-times', state: 'STATE_OK' }];
        const service = {
            dataIntegrityStatus: vi.fn().mockResolvedValue(status),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(service.dataIntegrityStatus).toHaveBeenCalledTimes(1);
        expect(service.dataIntegrityStatus).toHaveBeenCalledWith(false);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.dataIntegrityStatus).toEqual(status);
    });

    it('shows an error state instead of loading forever when loading fails', async () => {
        const service = {
            dataIntegrityStatus: vi
                .fn()
                .mockRejectedValue(new Error('Request failed')),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.$nextTick();
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(true);
    });

    it('recovers when retrying after a failure', async () => {
        const service = {
            dataIntegrityStatus: vi
                .fn()
                .mockRejectedValueOnce(new Error('Request failed'))
                .mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.refresh();
        await flushPromises();

        expect(service.dataIntegrityStatus).toHaveBeenLastCalledWith(true);

        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.dataIntegrityStatus).toEqual([]);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('translates the info of every data integrity check into German', () => {
        const english =
            enGB['frosh-tools'].tabs['data-integrity'].recommendations;
        const german =
            deDE['frosh-tools'].tabs['data-integrity'].recommendations;

        expect(Object.keys(german)).toEqual(Object.keys(english));
        for (const [id, texts] of Object.entries(german)) {
            expect(texts.description, id).not.toBe(english[id].description);
            expect(texts.solution, id).not.toBe(english[id].solution);
        }
    });
});
