import template from './template.html.twig';

const { Component } = Shopware;

// Generic button on top of the .ft-btn design-system styles.
// Forwards click via @click; other attributes (title, type, ...) fall through.
Component.register('ft-button', {
    props: {
        // primary | ghost | danger | null (default surface button)
        variant: { type: String, default: null },
        icon: { type: String, default: null },
        // Square icon-only button (.ft-btn--icon)
        iconOnly: { type: Boolean, default: false },
        disabled: { type: Boolean, default: false },
    },
    emits: ['click'],
    computed: {
        rootClass() {
            const classes = ['ft-btn'];
            if (this.variant) classes.push(`ft-btn--${this.variant}`);
            if (this.iconOnly) classes.push('ft-btn--icon');
            return classes.join(' ');
        },
    },
    template,
});
