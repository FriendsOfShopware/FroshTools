class ApiService {
    static handleResponse(response) {
        return response.data;
    }
}

global.Shopware = {
    Classes: { ApiService },
};

const FroshTools = require('./frosh-tools').default;

describe('FroshTools queue API', () => {
    let service;

    beforeEach(() => {
        service = Object.create(FroshTools.prototype);
        service.httpClient = {
            get: jest.fn().mockResolvedValue({ data: {} }),
            post: jest.fn().mockResolvedValue({ data: {} }),
            delete: jest.fn().mockResolvedValue({ data: {} }),
        };
        service.getApiBasePath = () => '/_action/frosh-tools';
        service.getBasicHeaders = () => ({});
    });

    it('encodes transport names in every queue route', async () => {
        const name = 'priority/messages?# queue';
        const encodedName = encodeURIComponent(name);

        await service.getQueueMessages(name);
        await service.retryQueueMessage(name, 'message/id');
        await service.deleteQueueMessage(name, 'message/id');
        await service.purgeQueueTransport(name);

        expect(service.httpClient.get).toHaveBeenCalledWith(
            `/_action/frosh-tools/queue/transport/${encodedName}/messages`,
            expect.any(Object)
        );
        expect(service.httpClient.post).toHaveBeenCalledWith(
            `/_action/frosh-tools/queue/transport/${encodedName}/messages/message%2Fid/retry`,
            {},
            expect.any(Object)
        );
        expect(service.httpClient.delete).toHaveBeenNthCalledWith(
            1,
            `/_action/frosh-tools/queue/transport/${encodedName}/messages/message%2Fid`,
            expect.any(Object)
        );
        expect(service.httpClient.delete).toHaveBeenNthCalledWith(
            2,
            `/_action/frosh-tools/queue/transport/${encodedName}`,
            expect.any(Object)
        );
    });
});
