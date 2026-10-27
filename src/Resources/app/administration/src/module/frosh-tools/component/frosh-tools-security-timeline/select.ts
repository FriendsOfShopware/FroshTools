import template from './select.html.twig';

// Native selection keeps keyboard and screen-reader behavior consistent.
// Actor search remains a separate field so remote results can update in place.
Shopware.Component.register('frosh-tools-security-activity-select', {
    template,
    props: {
        value: { type: String, default: null },
        options: { type: Array, default: () => [] },
        label: { type: String, required: true },
        placeholder: { type: String, default: '' },
        isLoading: { type: Boolean, default: false },
        showClearableButton: { type: Boolean, default: false },
        searchable: { type: Boolean, default: false },
        searchTerm: { type: String, default: '' },
    },
    emits: ['update:value', 'search'],
    computed: {
        visibleOptions() {
            return this.options.filter(
                (option: { hidden?: boolean }) => !option.hidden
            );
        },
    },
});
