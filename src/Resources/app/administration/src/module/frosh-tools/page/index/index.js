import '../../styles/design-system.scss';
import './frosh-tools.scss';
import template from './template.twig';

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
    inject: ['froshToolsService'],

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
            commandPaletteOpen: false,
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
        },
    },

    created() {
        this.adminMenuStore('collapseSidebar');
        document.addEventListener('keydown', this.onGlobalKeydown, true);
    },

    unmounted() {
        document.removeEventListener('keydown', this.onGlobalKeydown, true);
        this.adminMenuStore('expandSidebar');
    },

    computed: {
        searchTab() {
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
                    labelKey: 'frosh-tools.tabs.index.title',
                },
                {
                    route: 'frosh.tools.index.security',
                    labelKey: 'frosh-tools.tabs.security.title',
                },
                {
                    route: 'frosh.tools.index.shopmon',
                    labelKey: 'frosh-tools.tabs.shopmon.title',
                },
            ];

            const performance = [
                {
                    route: 'frosh.tools.index.cache',
                    labelKey: 'frosh-tools.tabs.cache.title',
                },
                {
                    route: 'frosh.tools.index.statistics',
                    labelKey: 'frosh-tools.tabs.statistics.title',
                },
            ];
            if (this.elasticsearchAvailable) {
                performance.push({
                    route: 'frosh.tools.index.elasticsearch',
                    labelKey: 'frosh-tools.tabs.elasticsearch.title',
                });
            }

            const operations = [
                {
                    route: 'frosh.tools.index.queue',
                    labelKey: 'frosh-tools.tabs.queue.title',
                },
                {
                    route: 'frosh.tools.index.scheduled',
                    labelKey: 'frosh-tools.tabs.scheduledTaskOverview.title',
                },
                {
                    route: 'frosh.tools.index.statemachines',
                    labelKey: 'frosh-tools.tabs.state-machines.title',
                },
            ];

            const diagnostics = [];
            if (this.logsAvailable) {
                diagnostics.push({
                    route: 'frosh.tools.index.logs',
                    labelKey: 'frosh-tools.tabs.logs.title',
                });
            }
            diagnostics.push({
                route: 'frosh.tools.index.featureflags',
                labelKey: 'frosh-tools.tabs.feature-flags.title',
            });

            const cdn = [];
            if (this.fastlyAvailable) {
                cdn.push({
                    route: 'frosh.tools.index.fastly',
                    labelKey: 'frosh-tools.tabs.fastly.title',
                });
            }

            return [
                { labelKey: 'frosh-tools.nav.overview', items: overview },
                { labelKey: 'frosh-tools.nav.performance', items: performance },
                { labelKey: 'frosh-tools.nav.operations', items: operations },
                { labelKey: 'frosh-tools.nav.diagnostics', items: diagnostics },
                { labelKey: 'frosh-tools.nav.cdn', items: cdn },
            ];
        },

        commandPaletteShortcut() {
            const platform =
                typeof navigator !== 'undefined'
                    ? navigator.platform || navigator.userAgent || ''
                    : '';

            if (/Mac|iPhone|iPad|iPod/i.test(platform)) {
                return '⌘K';
            }

            return 'Ctrl K';
        },
    },

    methods: {
        onSearch(term) {
            this.searchTerm = term;
        },

        openCommandPalette() {
            this.commandPaletteOpen = true;
        },

        closeCommandPalette() {
            this.commandPaletteOpen = false;
        },

        onGlobalKeydown(event) {
            // Ignore plain key presses inside editable fields except when the
            // palette itself is open (it handles Escape/arrows on its own).
            const isModifier = event.metaKey || event.ctrlKey;
            const key = String(event.key || '').toLowerCase();

            if (isModifier && key === 'k') {
                // Only capture while FroshTools is the active page so we do not
                // fight the rest of the admin when the user navigates away.
                event.preventDefault();
                event.stopPropagation();
                this.commandPaletteOpen = !this.commandPaletteOpen;
            }
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
