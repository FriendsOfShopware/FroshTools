import { reactive } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import './index.js';

const STATUS = {
    summary: { critical: 1, high: 2, medium: 0, low: 0, unknown: 0, ok: 4 },
    findings: [
        { category: 'runtime', severity: 'critical' },
        { category: 'dependencies', severity: 'high' },
        { category: 'dependencies', severity: 'ok' },
    ],
};

function createService(overrides = {}) {
    return {
        getSecurityStatus: vi.fn().mockResolvedValue(STATUS),
        getSecuritySbom: vi
            .fn()
            .mockResolvedValue({ data: new Blob(['{"bom":true}']) }),
        ...overrides,
    };
}

async function createWrapper({ service = createService(), query = {} } = {}) {
    return mountRegistered('frosh-tools-tab-security', {
        provide: { froshToolsService: service },
        stubs: {
            'frosh-tools-security-overview': true,
            'frosh-tools-security-dependencies': true,
            'frosh-tools-security-files': true,
        },
        global: {
            mocks: {
                $route: reactive({ query }),
                $router: { push: vi.fn().mockResolvedValue() },
            },
        },
    });
}

describe('frosh-tools-tab-security', () => {
    afterEach(() => {
        window.localStorage.clear();
    });

    it('loads the posture summary and badges', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.actionable).toBe(3);
        expect(wrapper.vm.findingBadge).toBe(3);
        expect(wrapper.vm.findingBadgeVariant).toBe('danger');
        expect(wrapper.vm.dependencyBadge).toBe(1);
        expect(wrapper.vm.activeTab).toBe('overview');
        expect(wrapper.find('ft-refresh-button-stub').exists()).toBe(true);
    });

    it('opens a deep-linked section and updates the query on tab change', async () => {
        const wrapper = await createWrapper({ query: { section: 'files' } });
        await flushPromises();

        expect(wrapper.vm.activeTab).toBe('files');
        expect(wrapper.find('ft-refresh-button-stub').exists()).toBe(false);

        wrapper.vm.selectTab('dependencies');
        expect(wrapper.vm.$router.push).toHaveBeenCalledWith({
            query: { section: 'dependencies' },
        });
        wrapper.vm.$route.query = { section: 'dependencies' };
        await wrapper.vm.$nextTick();
        expect(
            wrapper.find('frosh-tools-security-dependencies-stub').exists()
        ).toBe(true);
        wrapper.vm.$route.query = { section: 'files' };
        await wrapper.vm.$nextTick();
        expect(wrapper.find('frosh-tools-security-files-stub').exists()).toBe(
            true
        );
    });

    it('downloads the SBOM as a file', async () => {
        const createObjectURL = vi.fn().mockReturnValue('blob:sbom');
        const revokeObjectURL = vi.fn();
        vi.stubGlobal('URL', { createObjectURL, revokeObjectURL });
        const click = vi
            .spyOn(HTMLAnchorElement.prototype, 'click')
            .mockImplementation(() => {});

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.exportSbom();

        expect(createObjectURL).toHaveBeenCalled();
        expect(click).toHaveBeenCalled();
        expect(revokeObjectURL).toHaveBeenCalledWith('blob:sbom');
        click.mockRestore();
        vi.unstubAllGlobals();
    });
});
