import { describe, expect, it, vi } from 'vitest';
import { mockShopwareService } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

const REGISTERED_COMPONENTS = [
    'ft-icon',
    'ft-button',
    'ft-modal',
    'ft-page-head',
    'ft-panel',
    'ft-pill',
    'ft-empty',
    'ft-hero-state',
    'ft-refresh-button',
    'ft-th-sort',
    'ft-severity-bar',
    'frosh-tools-tab-index',
    'frosh-tools-tab-cache',
    'frosh-tools-tab-queue',
    'frosh-tools-tab-scheduled',
    'frosh-tools-tab-elasticsearch',
    'frosh-tools-tab-feature-flags',
    'frosh-tools-tab-logs',
    'frosh-tools-tab-state-machines',
    'frosh-tools-security-overview',
    'frosh-tools-security-dependencies',
    'frosh-tools-security-files',
    'frosh-tools-tab-security',
    'frosh-tools-tab-fastly',
    'frosh-tools-tab-statistics',
    'frosh-tools-tab-shopmon',
    'frosh-tools-index',
    'frosh-tools-webhook-list',
    'frosh-tools-webhook-detail',
];

describe('Administration entry point', () => {
    it('registers every Frosh Tools component and both modules', async () => {
        mockShopwareService('privileges', {
            addPrivilegeMappingEntry: vi.fn(),
        });
        mockShopwareService('searchTypeService', {
            upsertType: vi.fn(),
        });

        await import('./main');

        const registry = Shopware.Component.getComponentRegistry();

        for (const name of REGISTERED_COMPONENTS) {
            expect(registry.has(name), name).toBe(true);
        }

        expect(Shopware.Module.getModuleRegistry().has('frosh-tools')).toBe(
            true
        );
        expect(
            Shopware.Module.getModuleRegistry().has('frosh-tools-webhook')
        ).toBe(true);
    });
});
