import './component/ft-icon/index.js';
import './component/ft-button/index.js';
import './component/ft-modal/index.js';
import './component/ft-page-head/index.js';
import './component/ft-panel/index.js';
import './component/ft-pill/index.js';
import './component/ft-empty/index.js';
import './component/ft-hero-state/index.js';
import './component/ft-refresh-button/index.js';
import './component/ft-th-sort/index.js';
import './component/frosh-tools-tab-index/index.js';
import './component/frosh-tools-tab-cache/index.js';
import './component/frosh-tools-tab-queue/index.js';
import './component/frosh-tools-tab-scheduled/index.js';
import './component/frosh-tools-tab-elasticsearch/index.js';
import './component/frosh-tools-tab-feature-flags/index.js';
import './component/frosh-tools-tab-logs/index.js';
import './component/frosh-tools-tab-state-machines/index.js';
import './component/ft-severity-bar/index.js';
import './component/frosh-tools-security-overview/index.js';
import './component/frosh-tools-security-dependencies/index.js';
import './component/frosh-tools-security-files/index.js';
import './component/frosh-tools-tab-security/index.js';
import './component/frosh-tools-tab-fastly/index.js';
import './component/frosh-tools-tab-statistics/index.js';
import './component/frosh-tools-tab-shopmon/index.js';
import './page/index/index.js';
import './acl/index.js';
import { PRIVILEGE } from './acl/privileges.js';

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
                        privilege: PRIVILEGE.READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                cache: {
                    component: 'frosh-tools-tab-cache',
                    path: 'cache',
                    meta: {
                        privilege: PRIVILEGE.CACHE_READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                queue: {
                    component: 'frosh-tools-tab-queue',
                    path: 'queue',
                    meta: {
                        privilege: PRIVILEGE.QUEUE_READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                scheduled: {
                    component: 'frosh-tools-tab-scheduled',
                    path: 'scheduled',
                    meta: {
                        privilege: PRIVILEGE.SCHEDULED_TASK_READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                elasticsearch: {
                    component: 'frosh-tools-tab-elasticsearch',
                    path: 'elasticsearch',
                    meta: {
                        privilege: PRIVILEGE.ELASTICSEARCH_READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                featureflags: {
                    component: 'frosh-tools-tab-feature-flags',
                    path: 'feature-flags',
                    meta: {
                        privilege: PRIVILEGE.READ,
                        parentPath: 'frosh.tools.index.index',
                    },
                },
                logs: {
                    component: 'frosh-tools-tab-logs',
                    path: 'logs',
                    meta: {
                        privilege: PRIVILEGE.LOGS_READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                security: {
                    component: 'frosh-tools-tab-security',
                    path: 'security',
                    meta: {
                        privilege: PRIVILEGE.SECURITY_READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                statemachines: {
                    component: 'frosh-tools-tab-state-machines',
                    path: 'state-machines',
                    meta: {
                        privilege: PRIVILEGE.READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                fastly: {
                    component: 'frosh-tools-tab-fastly',
                    path: 'fastly',
                    meta: {
                        privilege: PRIVILEGE.FASTLY_READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                statistics: {
                    component: 'frosh-tools-tab-statistics',
                    path: 'statistics',
                    meta: {
                        privilege: PRIVILEGE.READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                shopmon: {
                    component: 'frosh-tools-tab-shopmon',
                    path: 'shopmon',
                    meta: {
                        privilege: PRIVILEGE.SHOPMON_READ,
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
            },
        },
    },

    settingsItem: [
        {
            group: 'plugins',
            to: 'frosh.tools.index.index',
            icon: 'regular-cog',
            name: 'frosh-tools',
            label: 'frosh-tools.title',
            privilege: PRIVILEGE.READ,
        },
    ],
});
