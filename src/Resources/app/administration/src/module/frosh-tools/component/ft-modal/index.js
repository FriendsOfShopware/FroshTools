import './style.scss';

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

    template: `
        <teleport to="body">
            <div class="ft ft-modal" @mousedown.self="onBackdrop">
                <div class="ft-modal__dialog" :class="\`ft-modal__dialog--\${variant}\`" role="dialog" aria-modal="true">
                    <header class="ft-modal__head" v-if="$slots.header || title">
                        <slot name="header">
                            <h2 class="ft-modal__title">{{ title }}</h2>
                        </slot>
                        <button class="ft-modal__close" @click="close" aria-label="Close">
                            <ft-icon name="close" />
                        </button>
                    </header>
                    <div class="ft-modal__body">
                        <slot />
                    </div>
                    <footer class="ft-modal__foot" v-if="$slots.footer">
                        <slot name="footer" />
                    </footer>
                </div>
            </div>
        </teleport>
    `,
});
