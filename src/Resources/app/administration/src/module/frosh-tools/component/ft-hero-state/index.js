import './style.scss';
import template from './template.html.twig';

const { Component } = Shopware;

// Used for big inline status callouts (Files tab, Elasticsearch disabled, etc).
Component.register('ft-hero-state', {
    props: {
        // success | warning | danger | info
        variant: { type: String, default: 'info' },
        icon: { type: String, default: 'alert' },
        title: { type: String, default: '' },
        body: { type: String, default: '' },
    },
    computed: {
        rootClass() {
            return `ft-hero-state ft-hero-state--${this.variant}`;
        },
    },
    template,
});
