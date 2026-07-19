/**
 * Integration mount helper for the plugin's administration components.
 *
 * Mounts components with the real plugin component tree (all ft-*
 * design-system components) and real translations resolved from the
 * plugin's own snippet files through vue-i18n. Only system boundaries
 * (HTTP services, repositories, router) are left to the specs to fake.
 */
import { mount } from '@vue/test-utils';

import '../src/mixin/sortable-table';
import '../src/module/frosh-tools/component/ft-icon';
import '../src/module/frosh-tools/component/ft-button';
import '../src/module/frosh-tools/component/ft-modal';
import '../src/module/frosh-tools/component/ft-page-head';
import '../src/module/frosh-tools/component/ft-panel';
import '../src/module/frosh-tools/component/ft-pill';
import '../src/module/frosh-tools/component/ft-empty';
import '../src/module/frosh-tools/component/ft-hero-state';
import '../src/module/frosh-tools/component/ft-refresh-button';
import '../src/module/frosh-tools/component/ft-th-sort';

const { join } = require('path');
const { existsSync } = require('fs');

const { createI18n } = require(join(
    process.env.ADMIN_PATH,
    'node_modules',
    'vue-i18n'
));

const pluginSnippetsEnGB = require('../src/module/frosh-tools/snippet/en-GB.json');
const pluginSnippetsDeDE = require('../src/module/frosh-tools/snippet/de-DE.json');

/**
 * Core app snippets (global.default.* etc.). The file was renamed in
 * Shopware 6.7, so try both names and simply skip when unavailable —
 * missing keys then render as the key itself, like in the real app.
 */
function loadCoreSnippets() {
    const snippetDir = join(process.env.ADMIN_PATH, 'src', 'app', 'snippet');

    for (const fileName of ['en.json', 'en-GB.json']) {
        const filePath = join(snippetDir, fileName);
        if (existsSync(filePath)) {
            // eslint-disable-next-line import/no-dynamic-require
            return require(filePath);
        }
    }

    return {};
}

/**
 * Deep merge: the plugin also overrides single core keys (e.g.
 * global.entities), a shallow spread would drop the whole core subtree.
 */
function deepMerge(target, source) {
    const result = { ...target };

    Object.entries(source).forEach(([key, value]) => {
        if (
            value !== null &&
            typeof value === 'object' &&
            !Array.isArray(value) &&
            typeof result[key] === 'object' &&
            result[key] !== null
        ) {
            result[key] = deepMerge(result[key], value);
        } else {
            result[key] = value;
        }
    });

    return result;
}

const i18n = createI18n({
    legacy: false,
    locale: 'en-GB',
    fallbackLocale: 'en-GB',
    missingWarn: false,
    fallbackWarn: false,
    messages: {
        'en-GB': deepMerge(loadCoreSnippets(), pluginSnippetsEnGB),
        'de-DE': pluginSnippetsDeDE,
    },
});

/**
 * $t/$tc backed by the real snippet catalog and the real vue-i18n
 * interpolation/pluralization engine. Wired as mocks because Shopware
 * itself also bridges $t onto the component instance instead of using
 * vue-i18n's global injection.
 */
export function froshI18nMocks() {
    return {
        $t: (key, ...args) => i18n.global.t(key, ...args),
        $tc: (key, ...args) => i18n.global.t(key, ...args),
    };
}

const FROSH_COMPONENTS = [
    'ft-icon',
    'ft-button',
    'ft-modal',
    'ft-page-head',
    'ft-panel',
    'ft-pill',
    'ft-empty',
    'ft-hero-state',
    'ft-refresh-button',
    'ft-th-sort',
];

/** Builds real component definitions keyed by their tag name. */
export async function froshComponents(...names) {
    const components = {};
    const built = await Promise.all(
        names.map((name) => Shopware.Component.build(name))
    );

    names.forEach((name, index) => {
        components[name] = built[index];
    });

    return components;
}

/**
 * Mounts a registered plugin component together with the real ft-*
 * component tree and real translations. Pass `provide`/`mocks` only for
 * system boundaries (services, repositories, router) and `stubs` for
 * Shopware core components that are not under test.
 */
export async function mountFrosh(
    name,
    {
        props,
        slots,
        provide = {},
        mocks = {},
        stubs = {},
        components = {},
        directives = {},
        attachTo,
        data,
    } = {}
) {
    const [component, realChildren] = await Promise.all([
        Shopware.Component.build(name),
        froshComponents(
            ...FROSH_COMPONENTS.filter((childName) => childName !== name)
        ),
    ]);

    return mount(component, {
        props,
        slots,
        attachTo,
        data,
        global: {
            provide,
            mocks: {
                ...froshI18nMocks(),
                ...mocks,
            },
            directives: {
                tooltip: {},
                ...directives,
            },
            stubs: {
                teleport: true,
                ...stubs,
            },
            components: {
                ...realChildren,
                ...components,
            },
        },
    });
}
