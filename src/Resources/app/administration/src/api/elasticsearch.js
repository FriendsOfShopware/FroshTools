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

    console(method, path, payload) {
        const apiRoute = `${this.getApiBasePath()}/console` + path;
        return this.httpClient.request(
            {
                url: apiRoute,
                method: method,
                headers: {
                    ...this.getBasicHeaders(),
                    'content-type': 'application/json',
                },
                data: payload,
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    flushAll() {
        const apiRoute = `${this.getApiBasePath()}/flush_all`;
        return this.httpClient.post(
            apiRoute,
            {},
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    reindex() {
        const apiRoute = `${this.getApiBasePath()}/reindex`;
        return this.httpClient.post(
            apiRoute,
            {},
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    switchAlias() {
        const apiRoute = `${this.getApiBasePath()}/switch_alias`;
        return this.httpClient.post(
            apiRoute,
            {},
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    cleanup() {
        const apiRoute = `${this.getApiBasePath()}/cleanup`;
        return this.httpClient.post(
            apiRoute,
            {},
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    reset() {
        const apiRoute = `${this.getApiBasePath()}/reset`;
        return this.httpClient.post(
            apiRoute,
            {},
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}

export default Elasticsearch;
