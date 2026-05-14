import '../../styles/design-system.scss';
import './frosh-tools.scss';
import template from './template.twig';

const { Component } = Shopware;

function readSetting(key) {
    try {
        return (
            Shopware.Store.get('context').app.config.settings?.froshTools?.[key] || false
        );
    } catch {
        return (
            Shopware.State.get('context').app.config.settings?.froshTools?.[key] || false
        );
    }
}

Component.register('frosh-tools-index', {
    template,
    inject: ['froshToolsService'],

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    created() {
        this.adminMenuStore('collapseSidebar');
    },

    unmounted() {
        this.adminMenuStore('expandSidebar');
    },

    computed: {
        fastlyAvailable()         { return readSetting('fastlyEnabled'); },
        logsAvailable()           { return readSetting('logsEnabled'); },
        elasticsearchAvailable()  { return readSetting('elasticsearchEnabled'); },

        navGroups() {
            const overview = [
                { route: 'frosh.tools.index.index',  labelKey: 'frosh-tools.tabs.index.title' },
            ];

            const performance = [
                { route: 'frosh.tools.index.cache',  labelKey: 'frosh-tools.tabs.cache.title' },
            ];
            if (this.elasticsearchAvailable) {
                performance.push({ route: 'frosh.tools.index.elasticsearch', labelKey: 'frosh-tools.tabs.elasticsearch.title' });
            }

            const operations = [
                { route: 'frosh.tools.index.queue',         labelKey: 'frosh-tools.tabs.queue.title' },
                { route: 'frosh.tools.index.scheduled',     labelKey: 'frosh-tools.tabs.scheduledTaskOverview.title' },
                { route: 'frosh.tools.index.statemachines', labelKey: 'frosh-tools.tabs.state-machines.title' },
            ];

            const diagnostics = [];
            if (this.logsAvailable) {
                diagnostics.push({ route: 'frosh.tools.index.logs', labelKey: 'frosh-tools.tabs.logs.title' });
            }
            diagnostics.push({ route: 'frosh.tools.index.files',        labelKey: 'frosh-tools.tabs.files.title' });
            diagnostics.push({ route: 'frosh.tools.index.featureflags', labelKey: 'frosh-tools.tabs.feature-flags.title' });

            const cdn = [];
            if (this.fastlyAvailable) {
                cdn.push({ route: 'frosh.tools.index.fastly', labelKey: 'frosh-tools.tabs.fastly.title' });
            }

            return [
                { label: 'Overview',    items: overview },
                { label: 'Performance', items: performance },
                { label: 'Operations',  items: operations },
                { label: 'Diagnostics', items: diagnostics },
                { label: 'CDN',         items: cdn },
            ];
        },
    },

    methods: {
        adminMenuStore(action) {
            // Pinia (Shopware 6.7+)
            try {
                const store = Shopware.Store.get('adminMenu');
                if (store && typeof store[action] === 'function') {
                    store[action]();
                    return;
                }
            } catch { /* fall through */ }

            // Vuex (Shopware 6.6 and older)
            try {
                Shopware.State.commit(`adminMenu/${action}`);
            } catch { /* no-op */ }
        },
    },
});
