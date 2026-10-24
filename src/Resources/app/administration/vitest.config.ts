import { defineShopwareConfig } from '@friendsofshopware/vitest-shopware-admin-bridge';

const config = defineShopwareConfig({
    runtime: {
        strictConsole: true,
    },
    vitest: {
        test: {
            server: { deps: { inline: [/vue-test-utils/, /vue-router/] } },
            setupFiles: ['./test/setup.js'],
        },
    },
});

// Keep the test renderer on the same Vue runtime as the components on 6.6.
config.resolve.alias['@vue/test-utils'] = config.resolve.alias[
    '@vue/test-utils'
].replace('vue-test-utils.cjs.js', 'vue-test-utils.esm-bundler.mjs');
// Vue Router's CommonJS entry otherwise bypasses the Vue compat alias on 6.6.
config.resolve.alias['vue-router'] = config.resolve.alias['vue-router'].replace(
    /(?:index\.js|dist\/vue-router(?:\.node\.mjs|\.cjs))$/,
    'dist/vue-router.mjs'
);
export default config;
