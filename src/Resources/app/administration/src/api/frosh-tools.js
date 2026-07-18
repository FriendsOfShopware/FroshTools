const { ApiService } = Shopware.Classes;

class FroshTools extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/frosh-tools') {
        super(httpClient, loginService, apiEndpoint);
    }

    getCacheInfo() {
        const apiRoute = `${this.getApiBasePath()}/cache`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    clearCache(folder) {
        const apiRoute = `${this.getApiBasePath()}/cache/${folder}`;
        return this.httpClient
            .delete(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    clearOPcache() {
        const apiRoute = `${this.getApiBasePath()}/cache_clear_opcache`;
        return this.httpClient
            .delete(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getQueueTransports() {
        const apiRoute = `${this.getApiBasePath()}/queue/transports`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getQueueMessages(name, limit = 10) {
        const apiRoute = `${this.getApiBasePath()}/queue/transport/${encodeURIComponent(name)}/messages`;
        return this.httpClient
            .get(apiRoute, {
                params: { limit },
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    retryQueueMessage(name, id) {
        const apiRoute = `${this.getApiBasePath()}/queue/transport/${encodeURIComponent(name)}/messages/${encodeURIComponent(id)}/retry`;
        return this.httpClient
            .post(
                apiRoute,
                {},
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    deleteQueueMessage(name, id) {
        const apiRoute = `${this.getApiBasePath()}/queue/transport/${encodeURIComponent(name)}/messages/${encodeURIComponent(id)}`;
        return this.httpClient
            .delete(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    purgeQueueTransport(name) {
        const apiRoute = `${this.getApiBasePath()}/queue/transport/${encodeURIComponent(name)}`;
        return this.httpClient
            .delete(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    resetQueue() {
        const apiRoute = `${this.getApiBasePath()}/queue`;
        return this.httpClient
            .delete(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    runScheduledTask(id) {
        const apiRoute = `${this.getApiBasePath()}/scheduled-task/${id}`;
        return this.httpClient
            .post(
                apiRoute,
                {},
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    scheduleScheduledTask(id, immediately = false) {
        const apiRoute = `${this.getApiBasePath()}/scheduled-task/schedule/${id}`;
        return this.httpClient
            .post(
                apiRoute,
                {
                    immediately: immediately,
                },
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    deactivateScheduledTask(id) {
        const apiRoute = `${this.getApiBasePath()}/scheduled-task/deactivate/${id}`;
        return this.httpClient
            .post(
                apiRoute,
                {},
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    scheduledTasksRegister() {
        const apiRoute = `${this.getApiBasePath()}/scheduled-tasks/register`;
        return this.httpClient
            .post(
                apiRoute,
                {},
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    healthStatus(cached = false) {
        if (!this.loginService.isLoggedIn()) {
            return;
        }

        let apiRoute = `${this.getApiBasePath()}/health/status`;

        if (cached) {
            apiRoute = `${this.getApiBasePath()}/health-ping/status`;
        }

        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    performanceStatus() {
        const apiRoute = `${this.getApiBasePath()}/performance/status`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getLogFiles() {
        const apiRoute = `${this.getApiBasePath()}/logs/files`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getLogFile(file, offset = 0, limit = 20) {
        const apiRoute = `${this.getApiBasePath()}/logs/file`;
        return this.httpClient
            .get(apiRoute, {
                params: {
                    file,
                    offset,
                    limit,
                },
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return response;
            });
    }

    getShopwareFiles() {
        const apiRoute = `${this.getApiBasePath()}/shopware-files`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return response;
            });
    }

    getExtensionFiles() {
        const apiRoute = `${this.getApiBasePath()}/extension-files`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return response;
            });
    }

    getFileContents(file) {
        const apiRoute = `${this.getApiBasePath()}/file-contents`;
        return this.httpClient
            .get(apiRoute, {
                params: {
                    file,
                },
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return response;
            });
    }

    restoreShopwareFile(file) {
        const apiRoute = `${this.getApiBasePath()}/shopware-file/restore`;
        return this.httpClient
            .get(apiRoute, {
                params: {
                    file,
                },
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return response;
            });
    }

    getComposerAudit(forceRefresh = false) {
        const apiRoute = `${this.getApiBasePath()}/composer-audit`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
                params: forceRefresh ? { refresh: 1 } : {},
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getSecurityStatus() {
        const apiRoute = `${this.getApiBasePath()}/security/status`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    /**
     * Downloads a CycloneDX 1.7 SBOM generated from the project composer.lock.
     * Returns the raw response so callers can stream the attachment body.
     */
    getSecuritySbom(includeDev = false) {
        const apiRoute = `${this.getApiBasePath()}/security/sbom`;
        return this.httpClient.get(apiRoute, {
            headers: this.getBasicHeaders(),
            params: includeDev ? { includeDev: 1 } : {},
            responseType: 'blob',
        });
    }

    getFeatureFlags() {
        const apiRoute = `${this.getApiBasePath()}/feature-flag/list`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getFastlyStatus() {
        const apiRoute = `${this.getApiBasePath()}/fastly/status`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    fastlyPurge(path) {
        const apiRoute = `${this.getApiBasePath()}/fastly/purge`;
        return this.httpClient
            .post(
                apiRoute,
                { path },
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    fastlyPurgeAll() {
        const apiRoute = `${this.getApiBasePath()}/fastly/purge-all`;
        return this.httpClient
            .post(
                apiRoute,
                {},
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getFastlyStatistics(timeframe) {
        const apiRoute = `${this.getApiBasePath()}/fastly/statistics`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
                params: { timeframe },
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getFastlySnippets() {
        const apiRoute = `${this.getApiBasePath()}/fastly/snippets`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getCacheStatistics() {
        const apiRoute = `${this.getApiBasePath()}/statistics/cache`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getDatabaseStatistics() {
        const apiRoute = `${this.getApiBasePath()}/statistics/database`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    getShopmonStatus() {
        const apiRoute = `${this.getApiBasePath()}/shopmon`;
        return this.httpClient
            .get(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    setupShopmon() {
        const apiRoute = `${this.getApiBasePath()}/shopmon`;
        return this.httpClient
            .post(
                apiRoute,
                {},
                {
                    headers: this.getBasicHeaders(),
                }
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    removeShopmon() {
        const apiRoute = `${this.getApiBasePath()}/shopmon`;
        return this.httpClient
            .delete(apiRoute, {
                headers: this.getBasicHeaders(),
            })
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

export default FroshTools;
