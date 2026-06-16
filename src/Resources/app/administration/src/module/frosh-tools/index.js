import './component/ft-icon';
import './component/ft-modal';
import './component/ft-page-head';
import './component/ft-panel';
import './component/ft-pill';
import './component/ft-empty';
import './component/ft-hero-state';
import './component/ft-refresh-button';
import './component/ft-th-sort';
import './component/frosh-tools-tab-index';
import './component/frosh-tools-tab-cache';
import './component/frosh-tools-tab-queue';
import './component/frosh-tools-tab-scheduled';
import './component/frosh-tools-tab-elasticsearch';
import './component/frosh-tools-tab-feature-flags';
import './component/frosh-tools-tab-logs';
import './component/frosh-tools-tab-state-machines';
import './component/ft-severity-bar';
import './component/frosh-tools-security-overview';
import './component/frosh-tools-security-dependencies';
import './component/frosh-tools-security-files';
import './component/frosh-tools-tab-security';
import './component/frosh-tools-tab-fastly';
import './component/frosh-tools-tab-statistics';
import './component/frosh-tools-tab-shopmon';
import './page/index';
import './acl';

Shopware.Module.register('frosh-tools', {
    type: 'plugin',
    name: 'frosh-tools.title',
    title: 'frosh-tools.title',
    description: '',
    color: '#303A4F',

    icon: 'regular-cog',

    routes: {
        index: {
            component: 'frosh-tools-index',
            path: 'index',
            children: {
                index: {
                    component: 'frosh-tools-tab-index',
                    path: 'index',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                cache: {
                    component: 'frosh-tools-tab-cache',
                    path: 'cache',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                queue: {
                    component: 'frosh-tools-tab-queue',
                    path: 'queue',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                scheduled: {
                    component: 'frosh-tools-tab-scheduled',
                    path: 'scheduled',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                elasticsearch: {
                    component: 'frosh-tools-tab-elasticsearch',
                    path: 'elasticsearch',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                featureflags: {
                    component: 'frosh-tools-tab-feature-flags',
                    path: 'feature-flags',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index',
                    },
                },
                logs: {
                    component: 'frosh-tools-tab-logs',
                    path: 'logs',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                security: {
                    component: 'frosh-tools-tab-security',
                    path: 'security',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                statemachines: {
                    component: 'frosh-tools-tab-state-machines',
                    path: 'state-machines',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                fastly: {
                    component: 'frosh-tools-tab-fastly',
                    path: 'fastly',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                statistics: {
                    component: 'frosh-tools-tab-statistics',
                    path: 'statistics',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                shopmon: {
                    component: 'frosh-tools-tab-shopmon',
                    path: 'shopmon',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
            },
        },
    },

    settingsItem: [
        {
            group: 'plugins',
            to: 'frosh.tools.index.cache',
            icon: 'regular-cog',
            name: 'frosh-tools',
            label: 'frosh-tools.title',
            privilege: 'frosh_tools:read',
        },
    ],
});
