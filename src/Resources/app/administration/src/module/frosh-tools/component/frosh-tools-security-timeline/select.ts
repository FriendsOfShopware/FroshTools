// Keep asynchronously loaded choices reactive while the dropdown is open.
Shopware.Component.extend('frosh-tools-security-activity-select', 'sw-single-select', {
    data() {
        return { hasSearchInput: false };
    },
    methods: {
        onSelectExpanded() {
            this.hasSearchInput = false;
            this.$super('onSelectExpanded');
        },
        search() {
            this.hasSearchInput = true;
            this.$super('search');
        },
    },
    computed: {
        visibleResults() {
            return this.searchFunction({
                options: this.options,
                labelProperty: this.labelProperty,
                valueProperty: this.valueProperty,
                // Core prefills the selected label on open without filtering.
                searchTerm: this.hasSearchInput && !this.disableSearchFunction ? this.searchTerm : '',
            }).filter((option: { hidden?: boolean }) => !option.hidden);
        },
    },
});
