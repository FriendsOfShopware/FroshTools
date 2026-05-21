import './style.scss';
import template from './template.html.twig';

const { Component } = Shopware;

Component.register('ft-pill', {
    props: {
        // success | warning | danger | info | accent | muted | null
        variant: { type: String, default: null },
        bare: { type: Boolean, default: false },
    },
    computed: {
        rootClass() {
            const classes = ['ft-pill'];
            if (this.variant) classes.push(`ft-pill--${this.variant}`);
            if (this.bare) classes.push('ft-pill--bare');
            return classes.join(' ');
        },
    },
    template,
});
