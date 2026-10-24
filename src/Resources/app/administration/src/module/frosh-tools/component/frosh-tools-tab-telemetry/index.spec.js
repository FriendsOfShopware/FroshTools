import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, mountRegistered } from '../../../../../test/helpers.js';
import './index.js';

function createStatus(overrides = {}) {
    return {
        enabled: false,
        posthogConfigured: true,
        host: 'https://eu.i.posthog.com',
        event: 'frosh_tools_telemetry',
        schemaVersion: '2',
        instanceId: null,
        payload: {
            'shopware.version': '6.7.1.0',
            'php.apcu_enabled': true,
            'shop.products': '101-1000',
            schema_version: '2',
        },
        ...overrides,
    };
}

function createService(overrides = {}) {
    return {
        getTelemetryStatus: vi.fn().mockResolvedValue(createStatus()),
        setTelemetryEnabled: vi
            .fn()
            .mockImplementation((enabled) =>
                Promise.resolve(createStatus({ enabled }))
            ),
        sendTelemetryNow: vi
            .fn()
            .mockResolvedValue({ sent: true, reason: null }),
        resetTelemetryInstanceId: vi
            .fn()
            .mockResolvedValue(createStatus({ instanceId: null })),
        ...overrides,
    };
}

async function createWrapper({
    service = createService(),
    canUpdate = true,
} = {}) {
    return mountRegistered('frosh-tools-tab-telemetry', {
        stubs: {
            'sw-switch-field': {
                name: 'sw-switch-field',
                props: ['value', 'label'],
                emits: ['update:value'],
                template:
                    '<button @click="$emit(\'update:value\', !value)">{{ label }}</button>',
            },
        },
        provide: {
            froshToolsService: service,
            acl: createAcl(canUpdate, 'frosh_tools_telemetry:update'),
        },
    });
}

describe('frosh-tools-tab-telemetry', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows the disabled opt-in state and the sorted payload preview', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.isEnabled).toBe(false);
        expect(wrapper.vm.payloadEntries.map((entry) => entry.key)).toEqual([
            'php.apcu_enabled',
            'schema_version',
            'shop.products',
            'shopware.version',
        ]);
        expect(wrapper.vm.formatValue(true)).toBe('true');
        expect(wrapper.text()).toContain('101-1000');

        const dashboardLink = wrapper.find(
            '.frosh-tab-telemetry__dashboard-link'
        );
        expect(dashboardLink.exists()).toBe(true);
        expect(dashboardLink.attributes('href')).toBe(
            'https://eu.posthog.com/shared/iY7ArzsigzNyWEnm0kAGBQaEib9qxQ'
        );
        expect(dashboardLink.attributes('target')).toBe('_blank');
    });

    it('enables telemetry through the opt-in toggle', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        await wrapper.vm.onToggleEnabled(true);
        await flushPromises();

        expect(service.setTelemetryEnabled).toHaveBeenCalledWith(true);
        expect(wrapper.vm.isEnabled).toBe(true);
    });

    it('reloads the status when saving the opt-in fails', async () => {
        const service = createService({
            setTelemetryEnabled: vi.fn().mockRejectedValue(new Error('nope')),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        await wrapper.vm.onToggleEnabled(true);
        await flushPromises();

        expect(service.getTelemetryStatus).toHaveBeenCalledTimes(2);
        expect(wrapper.vm.isEnabled).toBe(false);
    });

    it('sends the event now and maps failure reasons to snippet keys', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        await wrapper.vm.sendNow();
        expect(service.sendTelemetryNow).toHaveBeenCalledTimes(1);

        expect(wrapper.vm.reasonKey('not-enabled')).toBe('notEnabled');
        expect(wrapper.vm.reasonKey('not-configured')).toBe('notConfigured');
        expect(wrapper.vm.reasonKey('anything-else')).toBe('failed');
        expect(wrapper.vm.reasonKey(null)).toBe('failed');
    });

    it('resets the installation id and copies the payload as JSON', async () => {
        const writeText = vi.fn().mockResolvedValue();
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        const service = createService({
            getTelemetryStatus: vi
                .fn()
                .mockResolvedValue(createStatus({ instanceId: 'abc123' })),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        await wrapper.vm.resetInstanceId();
        expect(service.resetTelemetryInstanceId).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.status.instanceId).toBeNull();

        await wrapper.vm.copyPayload();
        expect(writeText).toHaveBeenCalledWith(
            expect.stringContaining('"shopware.version": "6.7.1.0"')
        );
        expect(wrapper.vm.copied).toBe(true);
    });

    it('shows a load error state', async () => {
        const service = createService({
            getTelemetryStatus: vi
                .fn()
                .mockRejectedValue(new Error('boom')),
        });
        const wrapper = await createWrapper({ service });
        await flushPromises();

        expect(wrapper.vm.loadError).toBe('boom');
        expect(wrapper.vm.status).toBeNull();
    });
});
