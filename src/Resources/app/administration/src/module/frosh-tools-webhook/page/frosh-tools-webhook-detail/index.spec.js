import { describe, expect, it, vi } from 'vitest';
import {
    createRepositoryMock,
    flushPromises,
    mountShopwareComponent,
    setRepositoryMocks,
} from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import './index';

function createWebhook(overrides = {}) {
    return {
        id: 'webhook-1',
        name: 'Order placed',
        eventName: 'checkout.order.placed',
        url: 'https://example.test',
        appId: null,
        app: null,
        isNew: () => false,
        ...overrides,
    };
}

async function createWrapper({
    webhookId = 'webhook-1',
    webhook = createWebhook(),
    privileges = ['frosh_tools_webhook.editor', 'frosh_tools_webhook.creator'],
} = {}) {
    const webhookRepository = createRepositoryMock({
        get: vi.fn().mockResolvedValue(webhook),
        create: vi.fn().mockReturnValue(
            createWebhook({
                id: 'new-webhook',
                name: '',
                isNew: () => true,
            })
        ),
        save: vi.fn().mockResolvedValue({}),
    });
    const eventLogRepository = createRepositoryMock({
        search: vi.fn().mockResolvedValue(Object.assign([], { total: 0 })),
    });

    setRepositoryMocks({
        webhook: webhookRepository,
        webhook_event_log: eventLogRepository,
    });

    const wrapper = await mountShopwareComponent(
        'frosh-tools-webhook-detail',
        {
            props: { webhookId },
            global: {
                provide: {
                    acl: {
                        can: (privilege) => privileges.includes(privilege),
                    },
                },
                mocks: {
                    $createTitle: (identifier) => identifier || 'Webhook',
                    $device: { getSystemKey: () => 'CTRL' },
                    $router: { push: vi.fn() },
                },
                stubs: {
                    'sw-button': true,
                    'sw-button-process': true,
                    'sw-card': { template: '<div><slot /></div>' },
                    'sw-card-view': { template: '<div><slot /></div>' },
                    'sw-container': { template: '<div><slot /></div>' },
                    'sw-text-field': true,
                    'sw-switch-field': true,
                    'sw-alert': true,
                    'sw-skeleton': true,
                    'sw-data-grid': true,
                    'sw-pagination': true,
                    'sw-label': true,
                },
            },
        }
    );

    await flushPromises();

    return { wrapper, webhookRepository };
}

describe('frosh-tools-webhook-detail', () => {
    it('loads an existing webhook and allows editors to save', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.vm.webhook.name).toBe('Order placed');
        expect(wrapper.vm.allowSave).toBe(true);
        expect(wrapper.vm.isNewWebhook).toBe(false);
    });

    it('creates a new webhook and blocks save without creator privileges', async () => {
        const { wrapper } = await createWrapper({
            webhookId: null,
            privileges: [],
        });

        expect(wrapper.vm.isNewWebhook).toBe(true);
        expect(wrapper.vm.allowSave).toBe(false);
    });

    it('never allows saving webhooks managed by an app', async () => {
        const { wrapper } = await createWrapper({
            webhook: createWebhook({ appId: 'app-1', app: { name: 'Demo' } }),
        });

        expect(wrapper.vm.isManagedByApp).toBe(true);
        expect(wrapper.vm.allowSave).toBe(false);
    });

    it('saves and reloads the webhook', async () => {
        const { wrapper, webhookRepository } = await createWrapper();

        await wrapper.vm.onSave();
        await flushPromises();

        expect(webhookRepository.save).toHaveBeenCalled();
        expect(wrapper.vm.isSaveSuccessful).toBe(true);
    });
});
