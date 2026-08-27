import { beforeEach, describe, expect, it, vi } from 'vitest';
import Elasticsearch from './elasticsearch';

describe('Elasticsearch API', () => {
    let service;

    beforeEach(() => {
        service = Object.create(Elasticsearch.prototype);
        service.httpClient = {
            get: vi.fn().mockResolvedValue({ data: {} }),
            post: vi.fn().mockResolvedValue({ data: {} }),
            delete: vi.fn().mockResolvedValue({ data: {} }),
            request: vi.fn().mockResolvedValue({ data: {} }),
        };
        service.getApiBasePath = () => '/_action/frosh-tools/elasticsearch';
        service.getBasicHeaders = () => ({ Authorization: 'Bearer token' });
    });

    it('posts orphaned cleanup with the selected index list', async () => {
        await service.cleanupOrphaned(['shopware-product', 'shopware-order']);

        expect(service.httpClient.post).toHaveBeenCalledWith(
            '/_action/frosh-tools/elasticsearch/cleanup_orphaned',
            { indices: ['shopware-product', 'shopware-order'] },
            expect.any(Object)
        );
    });

    it('sends console requests with the given method and path', async () => {
        await service.console('GET', '/_cat/indices', { pretty: true });

        expect(service.httpClient.request).toHaveBeenCalledWith({
            url: '/_action/frosh-tools/elasticsearch/console/_cat/indices',
            method: 'GET',
            headers: {
                Authorization: 'Bearer token',
                'content-type': 'application/json',
            },
            data: { pretty: true },
        });
    });
});
