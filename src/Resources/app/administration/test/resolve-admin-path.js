/**
 * Locates the Shopware administration app of the surrounding installation.
 *
 * The plugin has no test tooling of its own — the runner, Vue and the test
 * harness all come from the Shopware installation the plugin is installed in.
 * Works with the development template (src/Administration/…) and the
 * production template (vendor/shopware/administration/…).
 */
const { existsSync } = require('fs');
const { dirname, join } = require('path');

const RELATIVE_ADMIN_PATHS = [
    'src/Administration/Resources/app/administration',
    'vendor/shopware/administration/Resources/app/administration',
];

function isAdminPath(candidate) {
    return existsSync(join(candidate, 'jest.config.js'));
}

function resolveAdminPath() {
    if (process.env.ADMIN_PATH && isAdminPath(process.env.ADMIN_PATH)) {
        return process.env.ADMIN_PATH;
    }

    // Walk up from this file until a Shopware installation root is found.
    let dir = __dirname;
    for (;;) {
        const match = RELATIVE_ADMIN_PATHS.map((relative) =>
            join(dir, relative)
        ).find(isAdminPath);

        if (match) {
            return match;
        }

        const parent = dirname(dir);
        if (parent === dir) {
            throw new Error(
                'Could not locate a Shopware administration. Install the plugin into a Shopware ' +
                    'installation (custom/plugins) or set the ADMIN_PATH environment variable to the ' +
                    'administration app (the directory containing its jest.config.js).'
            );
        }
        dir = parent;
    }
}

module.exports = resolveAdminPath;
