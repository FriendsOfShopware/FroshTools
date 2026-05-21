import './style.scss';
import template from './template.html.twig';

const { Component } = Shopware;

Component.register('ft-page-head', {
    props: {
        title: { type: String, required: true },
        subtitle: { type: String, default: '' },
    },
    template,
});
