import FroshToolsService from './frosh-tools.js';
import Elasticsearch from './elasticsearch.js';

const { Application } = Shopware;

Application.addServiceProvider('froshToolsService', (container) => {
    const initContainer = Application.getContainer('init');

    return new FroshToolsService(
        initContainer.httpClient,
        container.loginService
    );
});

Application.addServiceProvider('froshElasticSearch', (container) => {
    const initContainer = Application.getContainer('init');

    return new Elasticsearch(initContainer.httpClient, container.loginService);
});
