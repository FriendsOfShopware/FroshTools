import './frosh-tools.scss'
import template from './template.html.twig'

const { Component } = Shopware

Component.register('frosh-tools-index', {
  template,

  computed: {
    elasticsearchAvailable() {
      return (
        Shopware.Store.get('context').app.config.settings
          ?.elasticsearchEnabled || false
      )
    },
  },
})
