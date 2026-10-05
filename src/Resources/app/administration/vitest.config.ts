import { defineShopwareConfig } from '@friendsofshopware/vitest-shopware-admin-bridge';

const config = defineShopwareConfig({
    runtime: {
        strictConsole: true,
    },
    vitest: {
        test: {
            server: { deps: { inline: [/vue-test-utils/] } },
            setupFiles: ['./test/setup.js'],
        },
    },
});

// Keep the test renderer on the same Vue runtime as the components on 6.6.
config.resolve.alias['@vue/test-utils'] = config.resolve.alias['@vue/test-utils'].replace(
    'vue-test-utils.cjs.js',
    'vue-test-utils.esm-bundler.mjs',
);
export default config;
