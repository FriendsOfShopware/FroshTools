import './component/frosh-tools-tab-index';
import './component/frosh-tools-tab-cache';
import './component/frosh-tools-tab-queue';
import './component/frosh-tools-tab-scheduled';
import './component/frosh-tools-tab-elasticsearch';
import './component/frosh-tools-tab-logs';
import './component/frosh-tools-tab-files';
import './component/frosh-tools-tab-feature-flags';
import './component/frosh-tools-tab-state-machines';
import './page/index';
import './acl'

Shopware.Module.register('frosh-tools', {
    type: 'plugin',
    name: 'frosh-tools.title',
    title: 'frosh-tools.title',
    description: '',
    color: '#303A4F',

    icon: 'default-device-dashboard',

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
                        parentPath: 'frosh.tools.index.index'
                    }
                },
                cache: {
                    component: 'frosh-tools-tab-cache',
                    path: 'cache',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index'
                    }
                },
                queue: {
                    component: 'frosh-tools-tab-queue',
                    path: 'queue',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index'
                    }
                },
                scheduled: {
                    component: 'frosh-tools-tab-scheduled',
                    path: 'scheduled',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index'
                    }
                },
                elasticsearch: {
                    component: 'frosh-tools-tab-elasticsearch',
                    path: 'elasticsearch',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index'
                    }
                },
                logs: {
                    component: 'frosh-tools-tab-logs',
                    path: 'logs',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index'
                    }
                },
                files: {
                    component: 'frosh-tools-tab-files',
                    path: 'files',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index'
                    }
                },
                featureflags: {
                    component: 'frosh-tools-tab-feature-flags',
                    path: 'feature-flags',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index'
                    }
                },
                statemachines: {
                    component: 'frosh-tools-tab-state-machines',
                    path: 'state-machines',
                    meta: {
                        privilege: 'frosh_tools:read',
                        parentPath: 'frosh.tools.index.index'
                    }
                },
            }
        },
    },

    settingsItem: [
        {
            group: 'plugins',
            to: 'frosh.tools.index.cache',
            icon: 'default-action-settings',
            name: 'frosh-tools.title'
        }
    ]
});
