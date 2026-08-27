import { mountShopwareComponent } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';

export const FT_STUBS = {
    'ft-page-head': {
        template:
            '<div class="ft-page-head"><slot name="subtitle" /><slot name="actions" /></div>',
    },
    'ft-panel': {
        template:
            '<section class="ft-panel"><slot name="title" /><slot name="actions" /><slot /></section>',
    },
    'ft-empty': true,
    'ft-hero-state': true,
    'ft-th-sort': { template: '<th><slot /></th>' },
    'ft-pill': { template: '<span class="ft-pill"><slot /></span>' },
    'ft-icon': true,
    'ft-modal': {
        template:
            '<div role="dialog"><slot name="header" /><slot /><slot name="footer" /></div>',
    },
    'ft-button': {
        template:
            '<button type="button" class="ft-btn" @click="$emit(\'click\', $event)"><slot /></button>',
    },
    'ft-refresh-button': true,
    'ft-severity-bar': true,
};

export function createAcl(canUpdate, updatePrivilege) {
    return {
        can: (privilege) => canUpdate || privilege !== updatePrivilege,
    };
}

export async function mountRegistered(name, options = {}) {
    const { provide = {}, stubs = {}, global = {}, ...rest } = options;

    return mountShopwareComponent(name, {
        ...rest,
        global: {
            ...global,
            provide: {
                ...(global.provide ?? {}),
                ...provide,
            },
            stubs: {
                ...FT_STUBS,
                ...(global.stubs ?? {}),
                ...stubs,
            },
        },
    });
}
