import './frosh-tools.scss';
import template from './template.twig';

const { Component } = Shopware;

Component.register('frosh-tools-index', {
  template,
  computed: {
    elasticsearchAvailable() {
      if (Shopware.Store && Shopware.Store.get('context')) {
        return (
          Shopware.Store.get('context').app.config.settings
            ?.elasticsearchEnabled || false
        );
      } else {
        return (
          Shopware.State.get('context').app.config.settings
            ?.elasticsearchEnabled || false
        );
      }
    },
  },
})
