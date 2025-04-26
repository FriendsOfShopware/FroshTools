import template from './template.html.twig'
import './style.scss'

const { Component } = Shopware

Component.override('sw-data-grid-inline-edit', {
  template,

})
