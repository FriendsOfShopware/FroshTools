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
        stubs: {
            'frosh-tools-data-integrity-affected': true,
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
            expect(Object.keys(texts), id).toEqual(Object.keys(english[id]));
            expect(texts.title, id).not.toBe(english[id].title);
            expect(texts.description, id).not.toBe(english[id].description);
            expect(texts.solution, id).not.toBe(english[id].solution);
        }
    });

    it('shows the translated check title instead of the backend label', async () => {
        const wrapper = await createWrapper({
            dataIntegrityStatus: vi.fn().mockResolvedValue([
                {
                    id: 'rule-invalid',
                    snippet: 'Backend label',
                    state: 'STATE_INFO',
                    current: '2',
                    recommended: '0',
                },
                {
                    id: 'third-party-check',
                    snippet: 'Third party label',
                    state: 'STATE_OK',
                    current: '0',
                    recommended: '0',
                },
            ]),
        });
        await flushPromises();

        expect(wrapper.vm.dataIntegrityRows.map((row) => row.title)).toEqual([
            'Rules with invalid conditions',
            'Third party label',
        ]);
        expect(wrapper.text()).toContain('Rules with invalid conditions');
        expect(wrapper.text()).not.toContain('Backend label');
    });

    it('lists the affected records only for checks with findings', async () => {
        const wrapper = await createWrapper({
            dataIntegrityStatus: vi.fn().mockResolvedValue([]),
        });
        await flushPromises();

        wrapper.vm.openInfo({
            id: 'rule-invalid',
            state: 'STATE_OK',
            current: '0',
            recommended: '0',
        });
        await wrapper.vm.$nextTick();
        expect(
            wrapper.find('frosh-tools-data-integrity-affected-stub').exists()
        ).toBe(false);

        wrapper.vm.openInfo({
            id: 'rule-invalid',
            state: 'STATE_INFO',
            current: '2',
            recommended: '0',
        });
        await wrapper.vm.$nextTick();
        const affected = wrapper.find(
            'frosh-tools-data-integrity-affected-stub'
        );
        expect(affected.exists()).toBe(true);
        expect(affected.attributes('check-id')).toBe('rule-invalid');
    });

    it('opens the info of checks with findings that have no texts of their own', async () => {
        const wrapper = await createWrapper({
            dataIntegrityStatus: vi.fn().mockResolvedValue([]),
        });
        await flushPromises();

        const pluginCheck = {
            id: 'third-party-check',
            state: 'STATE_INFO',
            recommended: '0',
        };

        expect(wrapper.vm.hasInfo({ ...pluginCheck, current: '3' })).toBe(true);
        expect(wrapper.vm.hasInfo({ ...pluginCheck, current: '0' })).toBe(
            false
        );
        expect(wrapper.vm.hasInfo({ id: 'rule-invalid', current: '0' })).toBe(
            true
        );
    });
});
