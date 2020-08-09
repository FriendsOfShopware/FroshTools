import './component/frosh-tools-tab-cache';
import './page/index';

Shopware.Module.register('frosh-tools', {
    type: 'plugin',
    name: 'frosh-tools.title',
    title: 'frosh-tools.title',
    description: '',
    color: '#303A4F',

    icon: 'default-communication-envelope',

    routes: {
        index: {
            component: 'frosh-tools-index',
            path: 'index',
            children: {
                // index: {
                //     component: 'frosh-tools-tab-cache',
                //     path: 'index',
                //     meta: {
                //         parentPath: 'frosh.tools.index'
                //     }
                // },
                cache: {
                    component: 'frosh-tools-tab-cache',
                    path: 'cache',
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
            to: 'frosh.tools.index',
            icon: 'default-action-settings',
            name: 'frosh-tools.title'
        }
    ]
});
