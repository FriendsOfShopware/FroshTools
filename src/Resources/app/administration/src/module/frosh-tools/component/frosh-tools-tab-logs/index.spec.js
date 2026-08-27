import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers';
import '../../../../mixin/sortable-table';
import './index';

function createService(overrides = {}) {
    return {
        getLogFiles: vi
            .fn()
            .mockResolvedValue([{ name: 'prod.log' }, { name: 'dev.log' }]),
        getLogFile: vi.fn().mockResolvedValue({
            data: [
                { level: 'error', message: 'Something failed' },
                { level: 'info', message: 'Started' },
            ],
            headers: { 'file-size': '2' },
        }),
        ...overrides,
    };
}

async function createWrapper(service = createService()) {
    return mountRegistered('frosh-tools-tab-logs', {
        provide: { froshToolsService: service },
        stubs: { 'sw-single-select': true, 'sw-pagination': true },
    });
}

describe('frosh-tools-tab-logs', () => {
    it('loads log files and entries for the selected file', async () => {
        const service = createService();
        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.logFiles).toHaveLength(2);

        wrapper.vm.selectedLogFile = 'prod.log';
        await wrapper.vm.onFileSelected();
        await flushPromises();

        expect(service.getLogFile).toHaveBeenCalledWith('prod.log', 0, 25);
        expect(wrapper.vm.logEntries).toHaveLength(2);
        expect(wrapper.vm.totalLogEntries).toBe(2);
    });

    it('maps levels and truncates long messages', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.levelVariant('critical')).toBe('danger');
        expect(wrapper.vm.levelVariant('warning')).toBe('warning');
        expect(wrapper.vm.levelVariant('info')).toBe('info');
        expect(wrapper.vm.truncate('short')).toBe('short');
        expect(wrapper.vm.truncate('x'.repeat(221)).endsWith('…')).toBe(true);
    });

    it('shows an error state when listing files fails', async () => {
        const wrapper = await createWrapper(
            createService({
                getLogFiles: vi.fn().mockRejectedValue(new Error('no logs')),
            })
        );
        await flushPromises();

        expect(wrapper.vm.loadError).toBe('no logs');
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(true);
    });
});
