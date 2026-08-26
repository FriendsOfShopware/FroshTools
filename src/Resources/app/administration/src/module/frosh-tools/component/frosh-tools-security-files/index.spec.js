import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { createAcl, mountRegistered } from '../../../../../test/helpers';
import '../../../../mixin/sortable-table';
import './index';

function createService() {
    return {
        getShopwareFiles: vi
            .fn()
            .mockResolvedValue({ data: [{ name: 'src/Kernel.php' }] }),
        getExtensionFiles: vi
            .fn()
            .mockResolvedValue({ data: [{ name: 'FroshTools.php' }] }),
        getFileContents: vi.fn().mockResolvedValue({
            data: { originalContent: 'foo', content: 'bar' },
        }),
        restoreShopwareFile: vi
            .fn()
            .mockResolvedValue({ data: { status: 'restored' } }),
    };
}

async function createWrapper({
    service = createService(),
    canUpdate = true,
} = {}) {
    return mountRegistered('frosh-tools-security-files', {
        provide: {
            froshToolsService: service,
            acl: createAcl(canUpdate, 'frosh_tools_security:update'),
        },
    });
}

describe('frosh-tools-security-files', () => {
    it('loads core and extension file lists', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.items).toEqual([{ name: 'src/Kernel.php' }]);
        expect(wrapper.vm.extensionItems).toEqual([{ name: 'FroshTools.php' }]);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('builds a pretty diff and restores a file', async () => {
        const service = createService();
        const wrapper = await createWrapper({ service });
        await flushPromises();

        const notifySuccess = vi.spyOn(wrapper.vm, 'createNotificationSuccess');

        await wrapper.vm.diff({ name: 'src/Kernel.php' });
        expect(wrapper.vm.showModal).toBe(true);
        expect(wrapper.vm.diffData.html).toContain('foo');
        expect(wrapper.vm.diffData.html).toContain('bar');

        await wrapper.vm.restoreFile('src/Kernel.php');
        expect(service.restoreShopwareFile).toHaveBeenCalledWith(
            'src/Kernel.php'
        );
        expect(notifySuccess).toHaveBeenCalledWith({ message: 'restored' });
        expect(wrapper.vm.showModal).toBe(false);
    });

    it('exposes restore only when the user can update', async () => {
        const wrapper = await createWrapper({ canUpdate: false });
        await flushPromises();

        expect(wrapper.vm.canUpdate).toBe(false);
    });
});
