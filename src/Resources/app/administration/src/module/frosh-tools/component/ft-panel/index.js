import './style.scss';
import template from './template.html.twig';

const { Component } = Shopware;

Component.register('ft-panel', {
    props: {
        title: { type: String, default: '' },
        count: { type: [Number, String], default: null },
        flush: { type: Boolean, default: false },
    },
    computed: {
        bodyClass() {
            return this.flush
                ? 'ft-panel__body ft-panel__body--flush'
                : 'ft-panel__body';
        },
        hasHead() {
            return this.title || this.$slots.title || this.$slots.actions;
        },
    },
    template,
});
