import './frosh-tools.scss';
import template from './template.twig';

const { Component } = Shopware;

Component.register('frosh-tools-index', {
    template,
    inject: ['froshToolsService'],

    computed: {
        fastlyAvailable() {
            try {
                return (
                    Shopware.Store.get('context').app.config.settings
                        ?.froshTools.fastlyEnabled || false
                );
            } catch {
                return (
                    Shopware.State.get('context').app.config.settings
                        ?.froshTools.fastlyEnabled || false
                );
            }
        },
        elasticsearchAvailable() {
            try {
                return (
                    Shopware.Store.get('context').app.config.settings
                        ?.froshTools.elasticsearchEnabled || false
                );
            } catch {
                return (
                    Shopware.State.get('context').app.config.settings
                        ?.froshTools.elasticsearchEnabled || false
                );
            }
        },
    },
});
