import template from './template.html.twig';

const { Component } = Shopware;

// Refresh button: spinner while :loading="true", refresh icon otherwise.
// Forwards click via @click.
Component.register('ft-refresh-button', {
    props: {
        loading: { type: Boolean, default: false },
        label: { type: String, default: '' },
    },
    emits: ['click'],
    computed: {
        labelText() {
            return this.label || this.$t('frosh-tools.refresh');
        },
    },
    template,
});
