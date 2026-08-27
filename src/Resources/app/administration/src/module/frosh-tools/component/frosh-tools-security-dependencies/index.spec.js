import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers';
import './index';

const AUDIT = {
    packages: 12,
    vulnerable: 2,
    advisories: [
        {
            packageName: 'symfony/http-kernel',
            installedVersion: '6.4.0',
            installedSources: ['project'],
            severity: 'high',
        },
        {
            packageName: 'symfony/http-kernel',
            installedVersion: '6.4.0',
            installedSources: ['project'],
            severity: 'medium',
        },
        {
            packageName: 'guzzlehttp/guzzle',
            installedVersion: '7.0.0',
            installedSources: ['plugin'],
            severity: 'low',
        },
    ],
};

async function createWrapper(result = AUDIT) {
    return mountRegistered('frosh-tools-security-dependencies', {
        provide: {
            froshToolsService: {
                getComposerAudit: vi.fn().mockResolvedValue(result),
            },
        },
    });
}

describe('frosh-tools-security-dependencies', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('groups advisories by package version and builds an update command', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.groupedAdvisories).toHaveLength(2);
        expect(wrapper.vm.affectedPackages).toEqual([
            'guzzlehttp/guzzle',
            'symfony/http-kernel',
        ]);
        expect(wrapper.vm.updateCommand).toBe(
            'composer update guzzlehttp/guzzle symfony/http-kernel --with-dependencies'
        );
        expect(wrapper.vm.severityVariant('moderate')).toBe('warning');
    });

    it('copies the update command to the clipboard', async () => {
        const writeText = vi.fn().mockResolvedValue();
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.vm.copyCommand(wrapper.vm.updateCommand);
        expect(writeText).toHaveBeenCalledWith(wrapper.vm.updateCommand);
        expect(wrapper.vm.copiedCommand).toBe(wrapper.vm.updateCommand);
    });
});
