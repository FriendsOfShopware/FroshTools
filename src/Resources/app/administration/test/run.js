/**
 * Runs the plugin's administration unit tests with the jest installation of
 * the surrounding Shopware installation. All arguments are passed through to
 * jest, e.g. `npm run unit -- --watch` or `npm run unit -- --coverage`.
 */
const { existsSync } = require('fs');
const { join } = require('path');
const { spawnSync } = require('child_process');
const resolveAdminPath = require('./resolve-admin-path');

const adminPath = resolveAdminPath();

// Shopware's jest config refuses to run without the generated component
// import map, so make sure it exists before handing over to jest. Older
// Shopware versions neither generate nor require it.
const componentImports = join(
    adminPath,
    'test',
    '_helper_',
    'componentWrapper',
    'component-imports.js'
);
const adminPackage = require(join(adminPath, 'package.json'));
const hasGeneratorScript = Boolean(
    adminPackage.scripts &&
        adminPackage.scripts['generate-component-import-resolver-map']
);

if (!existsSync(componentImports) && hasGeneratorScript) {
    const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm';
    const setup = spawnSync(
        npm,
        ['run', 'generate-component-import-resolver-map'],
        { cwd: adminPath, stdio: 'inherit' }
    );

    if (setup.status !== 0) {
        process.exit(setup.status ?? 1);
    }
}

const result = spawnSync(
    process.execPath,
    [
        join(adminPath, 'node_modules', 'jest', 'bin', 'jest.js'),
        '--config',
        join(__dirname, '..', 'jest.config.js'),
        ...process.argv.slice(2),
    ],
    {
        cwd: adminPath,
        stdio: 'inherit',
        env: { ...process.env, ADMIN_PATH: adminPath },
    }
);

process.exit(result.status ?? 1);
