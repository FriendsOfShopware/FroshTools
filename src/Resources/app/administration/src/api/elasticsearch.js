const { ApiService } = Shopware.Classes;

class Elasticsearch extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/frosh-tools/elasticsearch') {
        super(httpClient, loginService, apiEndpoint);
    }

    status() {
        const apiRoute = `${this.getApiBasePath()}/status`;
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    indices() {
        const apiRoute = `${this.getApiBasePath()}/indices`;
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    deleteIndex(indexName) {
        const apiRoute = `${this.getApiBasePath()}/index/` + indexName;
        return this.httpClient.delete(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default Elasticsearch;
