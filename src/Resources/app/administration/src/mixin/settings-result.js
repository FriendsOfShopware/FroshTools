const { Mixin } = Shopware;

const FALLBACK_LOCALE = 'en-GB';

Mixin.register('frosh-settings-result', {
    methods: {
        pillVariant(state) {
            switch (state) {
                case 'STATE_ERROR':
                    return 'danger';
                case 'STATE_WARNING':
                    return 'warning';
                case 'STATE_INFO':
                    return 'info';
                default:
                    return 'success';
            }
        },

        stateLabel(state) {
            switch (state) {
                case 'STATE_ERROR':
                    return this.$t('frosh-tools.error');
                case 'STATE_WARNING':
                    return this.$t('frosh-tools.warning');
                case 'STATE_INFO':
                    return this.$t('frosh-tools.info');
                default:
                    return this.$t('frosh-tools.good');
            }
        },

        snippetExists(key) {
            return this.$te(key) || this.$te(key, FALLBACK_LOCALE);
        },

        resultTitle(snippetPrefix, item) {
            const key = `${snippetPrefix}.${item?.id}.title`;

            return item && this.snippetExists(key)
                ? this.$t(key)
                : item?.snippet;
        },

        resultRecommendation(snippetPrefix, item) {
            const key = `${snippetPrefix}.${item?.id}`;
            if (!item || !this.snippetExists(`${key}.description`)) {
                return null;
            }

            return {
                description: this.$t(`${key}.description`),
                solution: this.snippetExists(`${key}.solution`)
                    ? this.$t(`${key}.solution`)
                    : null,
            };
        },
    },
});
