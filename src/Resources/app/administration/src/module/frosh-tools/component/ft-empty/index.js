import './style.css';
import template from './template.html.twig';

const { Component } = Shopware;

// Empty state. When :loading="true" it shows the shared spinner instead.
Component.register('ft-empty', {
    props: {
        icon: { type: String, default: '' },
        title: { type: String, default: '' },
        sub: { type: String, default: '' },
        loading: { type: Boolean, default: false },
    },
    template,
});
