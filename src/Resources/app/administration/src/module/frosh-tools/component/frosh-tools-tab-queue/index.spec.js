import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, mountRegistered } from '../../../../../test/helpers';
import '../../../../mixin/sortable-table';
import './index';

const TRANSPORTS = [
    {
        name: 'async',
        type: 'doctrine',
        size: 2,
        browsable: true,
        workerLastSeenSeconds: 10,
    },
    {
        name: 'failed',
        type: 'doctrine',
        size: 1,
        browsable: false,
        workerLastSeenSeconds: 400,
    },
];

function createService(overrides = {}) {
    return {
        getQueueTransports: vi.fn().mockResolvedValue(TRANSPORTS),
        getQueueMessages: vi.fn().mockResolvedValue({
            messages: [{ id: 'msg-1', class: 'App\\Message\\Ping' }],
        }),
        retryQueueMessage: vi.fn().mockResolvedValue({}),
        deleteQueueMessage: vi.fn().mockResolvedValue({}),
        purgeQueueTransport: vi.fn().mockResolvedValue({}),
        resetQueue: vi.fn().mockResolvedValue({}),
        ...overrides,
    };
}

async function createWrapper({
    service = createService(),
    canUpdate = true,
    searchTerm = '',
} = {}) {
    return mountRegistered('frosh-tools-tab-queue', {
        provide: {
            repositoryFactory: { create: () => ({}) },
            froshToolsService: service,
            acl: createAcl(canUpdate, 'frosh_tools_queue:update'),
            froshToolsSearch: { searchTerm },
        },
    });
}

describe('frosh-tools-tab-queue', () => {
    it('loads transports and filters them by search', async () => {
        const wrapper = await createWrapper({ searchTerm: 'failed' });
        await flushPromises();

        expect(wrapper.vm.visibleTransports).toEqual([TRANSPORTS[1]]);
        expect(wrapper.vm.workerState(TRANSPORTS[0])).toBe('active');
        expect(wrapper.vm.workerState(TRANSPORTS[1])).toBe('stale');
    });

    it('formats ages and short class names', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.formatAge(null)).toBe('–');
        expect(wrapper.vm.formatAge(12)).toBe('12s');
        expect(wrapper.vm.formatAge(125)).toBe('2m');
        expect(wrapper.vm.formatAge(3700)).toBe('1h 1m');
        expect(wrapper.vm.shortClassName('App\\Message\\Ping')).toBe('Ping');
    });

    it('browses a transport and retries a message', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        wrapper.vm.openBrowseModal(TRANSPORTS[0]);
        await flushPromises();

        expect(service.getQueueMessages).toHaveBeenCalledWith('async', 10);
        expect(wrapper.vm.browseMessages).toHaveLength(1);

        await wrapper.vm.retryMessage({
            id: 'msg-1',
            originalTransport: 'async',
        });
        await flushPromises();

        expect(service.retryQueueMessage).toHaveBeenCalledWith(
            'async',
            'msg-1'
        );
    });

    it('does not open the browse modal for non-browsable transports', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.openBrowseModal(TRANSPORTS[1]);

        expect(wrapper.vm.browseTransport).toBeNull();
    });

    it('hides reset and purge actions without update privileges', async () => {
        const wrapper = await createWrapper({ canUpdate: false });
        await flushPromises();

        expect(wrapper.vm.canUpdate).toBe(false);
        expect(wrapper.text()).not.toContain('frosh-tools.resetQueue');
    });
});
