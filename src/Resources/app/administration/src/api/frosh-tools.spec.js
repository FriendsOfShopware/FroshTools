import FroshToolsService from './frosh-tools';

/**
 * The service is constructed the same way the DI container does it, with the
 * HTTP client as the only mock — that is the system boundary. Base path and
 * auth headers are the real implementation.
 */
describe('FroshTools API service', () => {
    let httpClient;
    let service;

    beforeEach(() => {
        httpClient = {
            get: jest.fn().mockResolvedValue({ data: {} }),
            post: jest.fn().mockResolvedValue({ data: {} }),
            delete: jest.fn().mockResolvedValue({ data: {} }),
        };

        service = new FroshToolsService(httpClient, {
            getToken: () => 'test-token',
            isLoggedIn: () => true,
        });
    });

    it('encodes transport names in every queue route', async () => {
        const name = 'priority/messages?# queue';
        const encodedName = encodeURIComponent(name);

        await service.getQueueMessages(name);
        await service.retryQueueMessage(name, 'message/id');
        await service.deleteQueueMessage(name, 'message/id');
        await service.purgeQueueTransport(name);

        expect(httpClient.get).toHaveBeenCalledWith(
            `_action/frosh-tools/queue/transport/${encodedName}/messages`,
            expect.objectContaining({
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                }),
            })
        );
        expect(httpClient.post).toHaveBeenCalledWith(
            `_action/frosh-tools/queue/transport/${encodedName}/messages/message%2Fid/retry`,
            {},
            expect.objectContaining({
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                }),
            })
        );
        expect(httpClient.delete).toHaveBeenNthCalledWith(
            1,
            `_action/frosh-tools/queue/transport/${encodedName}/messages/message%2Fid`,
            expect.objectContaining({
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                }),
            })
        );
        expect(httpClient.delete).toHaveBeenNthCalledWith(
            2,
            `_action/frosh-tools/queue/transport/${encodedName}`,
            expect.objectContaining({
                headers: expect.objectContaining({
                    Authorization: 'Bearer test-token',
                }),
            })
        );
    });

    it('requests the security SBOM as a blob attachment', async () => {
        await service.getSecuritySbom();
        await service.getSecuritySbom(true);

        expect(httpClient.get).toHaveBeenNthCalledWith(
            1,
            '_action/frosh-tools/security/sbom',
            expect.objectContaining({
                params: {},
                responseType: 'blob',
            })
        );
        expect(httpClient.get).toHaveBeenNthCalledWith(
            2,
            '_action/frosh-tools/security/sbom',
            expect.objectContaining({
                params: { includeDev: 1 },
                responseType: 'blob',
            })
        );
    });
});
