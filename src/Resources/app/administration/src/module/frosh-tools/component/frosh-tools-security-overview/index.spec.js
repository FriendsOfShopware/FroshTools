import { describe, expect, it, vi } from 'vitest';
import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { FT_STUBS } from '../../../../../test/helpers.js';
import './index.js';

const FINDINGS = [
    { category: 'dependencies', severity: 'high', title: 'CVE-1' },
    { category: 'runtime', severity: 'low', title: 'PHP' },
    { category: 'runtime', severity: 'critical', title: 'EOL' },
    { category: 'configuration', severity: 'ok', title: 'Debug' },
];

async function createWrapper(findings = FINDINGS) {
    return mountShopwareComponent('frosh-tools-security-overview', {
        props: { findings },
        global: { stubs: FT_STUBS },
    });
}

describe('frosh-tools-security-overview', () => {
    it('groups non-dependency findings and sorts by severity', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.groupedFindings.map((g) => g.category)).toEqual([
            'runtime',
            'configuration',
        ]);
        expect(
            wrapper.vm.groupedFindings[0].findings.map((f) => f.title)
        ).toEqual(['EOL', 'PHP']);
        expect(wrapper.vm.dependencyIssueCount).toBe(1);
        expect(wrapper.vm.dependencySummary).toEqual([
            { severity: 'high', count: 1 },
        ]);
    });

    it('keeps an acknowledged dependency visible without counting it as an issue', async () => {
        const wrapper = await createWrapper([
            ...FINDINGS,
            {
                id: 'cve-ack',
                category: 'dependencies',
                severity: 'info',
                title: 'Acknowledged CVE',
                acknowledgement: 'Backported by the distribution',
            },
        ]);

        expect(wrapper.vm.dependencyIssueCount).toBe(1);
        expect(wrapper.vm.severityVariant('info')).toBe('info');
        const dependencies = wrapper.vm.groupedFindings.find(
            (group) => group.category === 'dependencies'
        );
        expect(dependencies.findings.map((finding) => finding.title)).toEqual([
            'Acknowledged CVE',
        ]);
        const pill = wrapper
            .findAll('.ft-pill')
            .find((node) => node.attributes('title'));
        expect(pill.attributes('title')).toBe('Backported by the distribution');
    });

    it('emits navigate when opening the dependencies section', async () => {
        const wrapper = await createWrapper();

        wrapper.vm.goToDependencies();
        expect(wrapper.emitted('navigate')[0]).toEqual(['dependencies']);
    });

    it('opens documentation urls in a new tab', async () => {
        const open = vi.spyOn(window, 'open').mockImplementation(() => {});
        const wrapper = await createWrapper();

        wrapper.vm.openUrl('https://example.test/advisory');
        expect(open).toHaveBeenCalledWith(
            'https://example.test/advisory',
            '_blank',
            'noopener'
        );
        open.mockRestore();
    });
});
