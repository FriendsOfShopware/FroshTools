const { Application } = Shopware;

import FroshToolsService from './api/frosh-tools';

Application.addServiceProvider('FroshToolsService', (container) => {
    const initContainer = Application.getContainer('init');

    return new FroshToolsService(initContainer.httpClient, container.loginService);
});

import './module/frosh-tools';
