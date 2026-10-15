import '../../styles/design-system.scss';
import './frosh-tools.scss';
import template from './template.twig';
import { PRIVILEGE } from '../../acl/privileges.js';

const { Component } = Shopware;

function readSetting(key) {
    try {
        return (
            Shopware.Store.get('context').app.config.settings?.froshTools?.[
                key
            ] || false
        );
    } catch {
        return (
            Shopware.State.get('context').app.config.settings?.froshTools?.[
                key
            ] || false
        );
    }
}

// Tabs that support filtering their tables through the admin search bar.
// Maps the route name to the search type tag and placeholder snippet.
const SEARCHABLE_TABS = {
    'frosh.tools.index.cache': {
        type: 'frosh_tools_cache',
        placeholderKey: 'frosh-tools.search.placeholder.cache',
    },
    'frosh.tools.index.queue': {
        type: 'frosh_tools_queue',
        placeholderKey: 'frosh-tools.search.placeholder.queue',
    },
    'frosh.tools.index.scheduled': {
        type: 'frosh_tools_scheduled_task',
        placeholderKey: 'frosh-tools.search.placeholder.scheduled',
    },
    'frosh.tools.index.elasticsearch': {
        type: 'frosh_tools_elasticsearch',
        placeholderKey: 'frosh-tools.search.placeholder.elasticsearch',
    },
    'frosh.tools.index.featureflags': {
        type: 'frosh_tools_feature_flags',
        placeholderKey: 'frosh-tools.search.placeholder.featureFlags',
    },
};

Component.register('frosh-tools-index', {
    template,
    inject: ['froshToolsService', 'acl'],

    provide() {
        // Tabs inject this component as `froshToolsSearch` and read
        // `searchTerm` from it (reactive because it is component data).
        return {
            froshToolsSearch: this,
        };
    },

    data() {
        return {
            searchTerm: '',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    watch: {
        // A new tab shows different data — a stale term would filter it
        // unexpectedly, so the search starts fresh on every tab switch.
        '$route.name'() {
            this.searchTerm = '';
            this.revealActiveNavigation();
        },
    },

    created() {
        this.adminMenuStore('collapseSidebar');
    },

    mounted() {
        this.revealActiveNavigation();
    },

    unmounted() {
        this.adminMenuStore('expandSidebar');
    },

    computed: {
        searchTab() {
            if (
                this.$route.name === 'frosh.tools.index.elasticsearch' &&
                this.acl.can(PRIVILEGE.ELASTICSEARCH_UPDATE) &&
                ['console', 'maintenance'].includes(this.$route.query?.section)
            )
                return null;
            return SEARCHABLE_TABS[this.$route.name] ?? null;
        },

        fastlyAvailable() {
            return readSetting('fastlyEnabled');
        },
        logsAvailable() {
            return readSetting('logsEnabled');
        },
        elasticsearchAvailable() {
            return readSetting('elasticsearchEnabled');
        },

        navGroups() {
            const overview = [
                {
                    route: 'frosh.tools.index.index',
                    icon: 'activity',
                    labelKey: 'frosh-tools.tabs.index.title',
                    privilege: PRIVILEGE.READ,
                },
                {
                    route: 'frosh.tools.index.security',
                    icon: 'shield',
                    labelKey: 'frosh-tools.tabs.security.title',
                    privilege: PRIVILEGE.SECURITY_READ,
                },
                {
                    route: 'frosh.tools.index.shopmon',
                    icon: 'gauge',
                    labelKey: 'frosh-tools.tabs.shopmon.title',
                    privilege: PRIVILEGE.SHOPMON_READ,
                },
            ];

            const performance = [
                {
                    route: 'frosh.tools.index.cache',
                    icon: 'layers',
                    labelKey: 'frosh-tools.tabs.cache.title',
                    privilege: PRIVILEGE.CACHE_READ,
                },
                {
                    route: 'frosh.tools.index.statistics',
                    icon: 'database',
                    labelKey: 'frosh-tools.tabs.statistics.title',
                    privilege: PRIVILEGE.READ,
                },
            ];
            if (this.elasticsearchAvailable) {
                performance.push({
                    route: 'frosh.tools.index.elasticsearch',
                    icon: 'search',
                    labelKey: 'frosh-tools.tabs.elasticsearch.title',
                    privilege: PRIVILEGE.ELASTICSEARCH_READ,
                });
            }

            const operations = [
                {
                    route: 'frosh.tools.index.apps',
                    icon: 'apps',
                    labelKey: 'frosh-tools.tabs.apps.title',
                    privilege: PRIVILEGE.APPS_READ,
                },
                {
                    route: 'frosh.tools.index.queue',
                    icon: 'queue',
                    labelKey: 'frosh-tools.tabs.queue.title',
                    privilege: PRIVILEGE.QUEUE_READ,
                },
                {
                    route: 'frosh.tools.index.scheduled',
                    icon: 'timer',
                    labelKey: 'frosh-tools.tabs.scheduledTaskOverview.title',
                    privilege: PRIVILEGE.SCHEDULED_TASK_READ,
                },
                {
                    route: 'frosh.tools.webhook.index',
                    icon: 'send',
                    labelKey: 'frosh-tools.nav.webhooks',
                    privilege: 'frosh_tools_webhook.viewer',
                },
            ];

            const diagnostics = [
                {
                    route: 'frosh.tools.index.statemachines',
                    icon: 'flow',
                    labelKey: 'frosh-tools.tabs.state-machines.title',
                    privilege: PRIVILEGE.READ,
                },
                {
                    route: 'frosh.tools.index.dataintegrity',
                    icon: 'database',
                    labelKey: 'frosh-tools.tabs.data-integrity.title',
                    privilege: PRIVILEGE.READ,
                },
            ];
            if (this.logsAvailable) {
                diagnostics.push({
                    route: 'frosh.tools.index.logs',
                    icon: 'file',
                    labelKey: 'frosh-tools.tabs.logs.title',
                    privilege: PRIVILEGE.LOGS_READ,
                });
            }
            diagnostics.push({
                route: 'frosh.tools.index.featureflags',
                icon: 'flag',
                labelKey: 'frosh-tools.tabs.feature-flags.title',
                privilege: PRIVILEGE.READ,
            });

            const cdn = [];
            if (this.fastlyAvailable) {
                cdn.push({
                    route: 'frosh.tools.index.fastly',
                    icon: 'cloud',
                    labelKey: 'frosh-tools.tabs.fastly.title',
                    privilege: PRIVILEGE.FASTLY_READ,
                });
            }

            performance.push(...cdn);

            return [
                { labelKey: 'frosh-tools.nav.overview', items: overview },
                {
                    labelKey: 'frosh-tools.nav.infrastructure',
                    items: performance,
                },
                { labelKey: 'frosh-tools.nav.operations', items: operations },
                { labelKey: 'frosh-tools.nav.diagnostics', items: diagnostics },
            ]
                .map((group) => ({
                    ...group,
                    items: group.items.filter((item) =>
                        this.acl.can(item.privilege)
                    ),
                }))
                .filter((group) => group.items.length);
        },
    },

    methods: {
        navigateToTool(route) {
            if (
                this.navGroups.some((group) =>
                    group.items.some((item) => item.route === route)
                )
            ) {
                this.$router.push({ name: route });
            }
        },

        revealActiveNavigation() {
            // Keep deep-linked destinations visible in the narrow navigation
            // strip and in short desktop sidebars, without animated scrolling.
            this.$nextTick(() => {
                this.$refs.navigation
                    ?.querySelector('.is-active')
                    ?.scrollIntoView?.({
                        block: 'nearest',
                        inline: 'nearest',
                        behavior: 'instant',
                    });
            });
        },

        onSearch(term) {
            this.searchTerm = term;
        },

        adminMenuStore(action) {
            // Pinia (Shopware 6.7+)
            try {
                const store = Shopware.Store.get('adminMenu');
                if (store && typeof store[action] === 'function') {
                    store[action]();
                    return;
                }
            } catch {
                /* fall through */
            }

            // Vuex (Shopware 6.6 and older)
            try {
                Shopware.State.commit(`adminMenu/${action}`);
            } catch {
                /* no-op */
            }
        },
    },
});
