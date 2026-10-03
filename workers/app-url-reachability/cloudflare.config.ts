import { bindings, defineConfig } from 'cf/config';

export default defineConfig({
    worker: {
        name: 'frosh-tools-app-url-reachability',
        compatibilityDate: '2026-10-03',
        entrypoint: 'src/index.mjs',
        domains: ['app-url-check.fos.gg'],
        env: {
            CHECK_TOKEN: bindings.secret(),
        },
    },
});
