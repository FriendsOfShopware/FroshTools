import './frosh-tools.scss';
import template from './template.twig';

const { Component } = Shopware;

Component.register('frosh-tools-index', {
    template,
    computed: {
        elasticsearchAvailable() {
            try {
                return (
                    Shopware.Store.get('context').app.config.settings
                        ?.elasticsearchEnabled || false
                );
            } catch {
                return (
                    Shopware.State.get('context').app.config.settings
                        ?.elasticsearchEnabled || false
                );
            }
        },
    },
});
