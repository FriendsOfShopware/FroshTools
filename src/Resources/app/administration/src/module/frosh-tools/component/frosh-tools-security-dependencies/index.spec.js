import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import './index.js';

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
            installedSources: ['shopsy/shopsyklaviyo6'],
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

    it('shows project and bundled origins for each affected version', async () => {
        const wrapper = await createWrapper({
            packages: 1,
            vulnerable: 1,
            advisories: [
                {
                    packageName: 'guzzlehttp/guzzle',
                    installedVersion: '7.9.0',
                    installedSources: ['project'],
                },
                {
                    packageName: 'guzzlehttp/guzzle',
                    installedVersion: '7.10.0',
                    installedSources: [
                        'shopsy/shopsyklaviyo6',
                        'vendor/other-plugin',
                    ],
                },
            ],
        });
        await flushPromises();

        const groups = wrapper.findAll('.frosh-security-dependencies__group');
        expect(groups).toHaveLength(2);
        expect(groups[0].text()).toContain('7.9.0');
        expect(groups[0].text()).toContain('Project root');
        expect(groups[1].text()).toContain('7.10.0');
        expect(groups[1].text()).toContain(
            'Bundled from shopsy/shopsyklaviyo6'
        );
        expect(groups[1].text()).toContain('Bundled from vendor/other-plugin');

        wrapper.unmount();
    });

    it('renders cached advisories without source metadata', async () => {
        const wrapper = await createWrapper({
            packages: 1,
            vulnerable: 1,
            advisories: [
                {
                    packageName: 'guzzlehttp/guzzle',
                    installedVersion: '7.10.0',
                },
            ],
        });
        await flushPromises();

        expect(
            wrapper.find('.frosh-security-dependencies__group').text()
        ).toContain('7.10.0');
        expect(
            wrapper.find('.frosh-security-dependencies__group-sources').exists()
        ).toBe(false);

        wrapper.unmount();
    });
});
