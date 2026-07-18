import './style.scss';
import template from './template.html.twig';

const { Component } = Shopware;

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

Component.register('ft-modal', {
    props: {
        title: { type: String, default: '' },
        variant: { type: String, default: 'default' }, // default | small | large
        closeOnBackdrop: { type: Boolean, default: true },
    },

    emits: ['close'],

    computed: {
        titleId() {
            return `ft-modal-title-${this.$.uid}`;
        },
    },

    mounted() {
        document.addEventListener('keydown', this.onKeydown);
        this.previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        this.previousActiveElement = document.activeElement;

        this.$nextTick(() => {
            this.focusInitialElement();
        });
    },

    unmounted() {
        document.removeEventListener('keydown', this.onKeydown);
        document.body.style.overflow = this.previousOverflow || '';
        this.restorePreviousFocus();
    },

    methods: {
        onKeydown(event) {
            if (event.key === 'Escape') {
                event.stopPropagation();
                this.close();
                return;
            }

            if (event.key === 'Tab') {
                this.keepFocusInside(event);
            }
        },

        focusableElements() {
            const dialog = this.$refs.dialog;
            if (!dialog) return [];

            return Array.from(
                dialog.querySelectorAll(FOCUSABLE_SELECTOR)
            ).filter(
                (element) =>
                    !element.hasAttribute('disabled') &&
                    element.getAttribute('aria-hidden') !== 'true'
            );
        },

        focusInitialElement() {
            const [firstFocusable] = this.focusableElements();
            const target = firstFocusable ?? this.$refs.dialog;

            if (target && typeof target.focus === 'function') {
                target.focus();
            }
        },

        keepFocusInside(event) {
            const dialog = this.$refs.dialog;
            if (!dialog) return;

            const focusable = this.focusableElements();

            if (focusable.length === 0) {
                event.preventDefault();
                dialog.focus();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            // Focus left the dialog (e.g. browser chrome) — pull it back in.
            if (!dialog.contains(active)) {
                event.preventDefault();
                first.focus();
                return;
            }

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        },

        restorePreviousFocus() {
            const element = this.previousActiveElement;

            if (
                element &&
                document.contains(element) &&
                typeof element.focus === 'function'
            ) {
                element.focus();
            }

            this.previousActiveElement = null;
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
