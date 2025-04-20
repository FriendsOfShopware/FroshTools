import './component/frosh-tools-tab-index';
import './component/frosh-tools-tab-cache';
import './component/frosh-tools-tab-queue';
import './component/frosh-tools-tab-scheduled';
import './component/frosh-tools-tab-elasticsearch';
import './component/frosh-tools-tab-logs';
import './component/frosh-tools-tab-state-machines';
import './component/frosh-tools-tab-files';
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
                logs: {
                    component: 'frosh-tools-tab-logs',
                    path: 'logs',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'sw.settings.index.plugins',
                    },
                },
                files: {
                    component: 'frosh-tools-tab-files',
                    path: 'files',
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
