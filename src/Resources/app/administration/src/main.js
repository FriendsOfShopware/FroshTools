const { Application } = Shopware;

import FroshToolsService from './api/frosh-tools';
import './overrides/sw-data-grid-inline-edit';
import './overrides/sw-version';

Application.addServiceProvider('FroshToolsService', (container) => {
    const initContainer = Application.getContainer('init');

    return new FroshToolsService(initContainer.httpClient, container.loginService);
});

import './module/frosh-tools';

import localeDE from './snippet/de_DE.json';
import localeEN from './snippet/en_GB.json';

Shopware.Locale.extend('de-DE', localeDE);
Shopware.Locale.extend('en-GB', localeEN);