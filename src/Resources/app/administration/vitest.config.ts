import { defineShopwareConfig } from '@friendsofshopware/vitest-shopware-admin-bridge';

export default defineShopwareConfig({
    runtime: {
        strictConsole: true,
    },
    vitest: {
        test: {
            setupFiles: ['./test/setup.js'],
        },
    },
});
