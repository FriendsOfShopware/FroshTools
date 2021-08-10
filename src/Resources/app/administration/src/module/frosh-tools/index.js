import './component/frosh-tools-tab-index';
import './component/frosh-tools-tab-cache';
import './component/frosh-tools-tab-queue';
import './component/frosh-tools-tab-scheduled';
import './component/frosh-tools-tab-elasticsearch';
import './component/frosh-tools-tab-logs';
import './page/index';

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
                        parentPath: 'frosh.tools.index'
                    }
                },
                cache: {
                    component: 'frosh-tools-tab-cache',
                    path: 'cache',
                    meta: {
                        parentPath: 'frosh.tools.index'
                    }
                },
                queue: {
                    component: 'frosh-tools-tab-queue',
                    path: 'queue',
                    meta: {
                        parentPath: 'frosh.tools.index'
                    }
                },
                scheduled: {
                    component: 'frosh-tools-tab-scheduled',
                    path: 'scheduled',
                    meta: {
                        parentPath: 'frosh.tools.index'
                    }
                },
                elasticsearch: {
                    component: 'frosh-tools-tab-elasticsearch',
                    path: 'elasticsearch',
                    meta: {
                        parentPath: 'frosh.tools.index'
                    }
                },
                logs: {
                    component: 'frosh-tools-tab-logs',
                    path: 'logs',
                    meta: {
                        parentPath: 'frosh.tools.index'
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
