/**
 * Plugin test config: reuses the Shopware administration's jest config and
 * only points it at this plugin's spec files. This keeps the plugin on the
 * exact same harness (transformers, Shopware globals, jsdom setup) that the
 * Shopware installation provides, instead of duplicating it.
 */
const { join } = require('path');
const resolveAdminPath = require('./test/resolve-admin-path');

const adminPath = resolveAdminPath();

process.env.ADMIN_PATH = process.env.ADMIN_PATH || adminPath;
process.env.PROJECT_ROOT = process.env.PROJECT_ROOT || adminPath;

// eslint-disable-next-line import/no-dynamic-require
const coreConfig = require(join(adminPath, 'jest.config.js'));

module.exports = {
    ...coreConfig,
    rootDir: adminPath,
    roots: [join(__dirname, 'src')],
    testMatch: [join(__dirname, 'src/**/*.spec.js')],
    moduleNameMapper: {
        ...coreConfig.moduleNameMapper,
        '^frosh-test/(.*)$': join(__dirname, 'test/$1'),
    },
    coverageDirectory: join(__dirname, 'build', 'artifacts', 'jest'),
    collectCoverageFrom: [
        join(__dirname, 'src/**/*.js'),
        `!${join(__dirname, 'src/**/*.spec.js')}`,
    ],
};
