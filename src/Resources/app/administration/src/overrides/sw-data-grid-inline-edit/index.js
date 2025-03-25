import template from './template.html.twig'

const { Component } = Shopware

Component.override('sw-data-grid-inline-edit', {
  template,
})
