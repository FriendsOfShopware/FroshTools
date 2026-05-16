import './style.scss';
import template from './template.html.twig';

const { Component } = Shopware;

Component.register('ft-stat', {
    props: {
        label: { type: String, required: true },
        value: { type: [String, Number], required: true },
        hint: { type: String, default: '' },
        // success | warning | danger | info | accent | null
        variant: { type: String, default: null },
    },
    computed: {
        rootClass() {
            const base = 'ft-stat';
            return this.variant ? `${base} ${base}--${this.variant}` : base;
        },
    },
    template,
});
