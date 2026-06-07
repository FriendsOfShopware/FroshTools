import './style.scss';
import template from './template.html.twig';

const { Component } = Shopware;

Component.register('ft-modal', {
    props: {
        title: { type: String, default: '' },
        variant: { type: String, default: 'default' }, // default | small | large
        closeOnBackdrop: { type: Boolean, default: true },
    },

    emits: ['close'],

    mounted() {
        document.addEventListener('keydown', this.onKeydown);
        this.previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
    },

    unmounted() {
        document.removeEventListener('keydown', this.onKeydown);
        document.body.style.overflow = this.previousOverflow || '';
    },

    methods: {
        onKeydown(event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                this.close();
            }
        },
        onBackdrop() {
            if (this.closeOnBackdrop) this.close();
        },
        close() {
            this.$emit('close');
        },
    },

    template,
});
