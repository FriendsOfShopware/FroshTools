const { ApiService } = Shopware.Classes;

class FroshTools extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/frosh-tools') {
        super(httpClient, loginService, apiEndpoint);
    }

    getCacheInfo() {
        const apiRoute = `${this.getApiBasePath()}/cache`;
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    clearCache(folder) {
        const apiRoute = `${this.getApiBasePath()}/cache/${folder}`;
        return this.httpClient.delete(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    resetQueue() {
        const apiRoute = `${this.getApiBasePath()}/queue`;
        return this.httpClient.delete(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    runScheduledTask(id) {
        const apiRoute = `${this.getApiBasePath()}/scheduled-task/${id}`;
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

    healthStatus() {
        const apiRoute = `${this.getApiBasePath()}/health/status`;
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    getLogFiles() {
        const apiRoute = `${this.getApiBasePath()}/logs/files`;
        return this.httpClient.get(
            apiRoute,
            {
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }

    getLogFile(file, offset = 0, limit = 20) {
        const apiRoute = `${this.getApiBasePath()}/logs/file`;
        return this.httpClient.get(
            apiRoute,
            {
                params: {
                    file,
                    offset,
                    limit
                },
                headers: this.getBasicHeaders()
            }
        ).then((response) => {
            return response;
        });
    }
}

export default FroshTools;
