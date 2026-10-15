import template from './template.html.twig';
import './style.scss';

Shopware.Component.register('ft-section-nav', {
    template,
    props: {
        items: { type: Array, required: true },
        active: { type: String, required: true },
        label: { type: String, required: true },
    },
});
