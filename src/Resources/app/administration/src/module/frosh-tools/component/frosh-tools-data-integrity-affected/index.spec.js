import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import './index.js';
import entities from './entities.js';

function createRepositoryFactory(records) {
    const search = vi.fn().mockResolvedValue(records);

    return {
        search,
        factory: { create: vi.fn(() => ({ search })) },
    };
}

async function createWrapper(service, repositoryFactory) {
    const wrapper = await mountRegistered(
        'frosh-tools-data-integrity-affected',
        {
            props: { checkId: 'rule-invalid' },
            provide: {
                froshToolsService: service,
                repositoryFactory,
            },
            global: {
                mocks: {
                    $router: {
                        resolve: (route) => ({
                            href: `#/${route.name}/${route.params?.id ?? ''}`,
                        }),
                    },
                },
                stubs: { 'sw-pagination': true },
            },
        }
    );
    await flushPromises();

    return wrapper;
}

describe('frosh-tools-data-integrity-affected', () => {
    it('lists the affected records with labels and admin links', async () => {
        const service = {
            dataIntegrityAffected: vi.fn().mockResolvedValue({
                entity: 'rule',
                total: 2,
                ids: ['a', 'b'],
            }),
        };
        const repository = createRepositoryFactory([
            { id: 'b', name: 'Second rule', translated: {} },
        ]);

        const wrapper = await createWrapper(service, repository.factory);

        expect(service.dataIntegrityAffected).toHaveBeenCalledWith(
            'rule-invalid',
            1,
            10
        );
        expect(repository.factory.create).toHaveBeenCalledWith('rule');
        expect(repository.search.mock.calls[0][0].ids).toEqual(['a', 'b']);
        expect(wrapper.vm.rows).toEqual([
            {
                id: 'a',
                label: null,
                detail: null,
                href: '#/sw.settings.rule.detail/a',
            },
            {
                id: 'b',
                label: 'Second rule',
                detail: null,
                href: '#/sw.settings.rule.detail/b',
            },
        ]);

        const links = wrapper.findAll('a.ft-affected__link');
        expect(links.map((link) => link.text())).toEqual(['a', 'Second rule']);
        expect(links[0].attributes('target')).toBe('_blank');
    });

    it('shows plain ids when the records cannot be loaded', async () => {
        const repository = createRepositoryFactory([]);
        repository.search.mockRejectedValue(new Error('Forbidden'));

        const wrapper = await createWrapper(
            {
                dataIntegrityAffected: vi.fn().mockResolvedValue({
                    entity: 'rule',
                    total: 1,
                    ids: ['a'],
                }),
            },
            repository.factory
        );

        expect(wrapper.vm.rows).toEqual([
            {
                id: 'a',
                label: null,
                detail: null,
                href: '#/sw.settings.rule.detail/a',
            },
        ]);
    });

    it('shows plain ids without links for unknown entities', async () => {
        const repository = createRepositoryFactory([]);

        const wrapper = await createWrapper(
            {
                dataIntegrityAffected: vi.fn().mockResolvedValue({
                    entity: 'third_party_entity',
                    total: 1,
                    ids: ['a'],
                }),
            },
            repository.factory
        );

        expect(repository.factory.create).not.toHaveBeenCalled();
        expect(wrapper.vm.rows).toEqual([
            { id: 'a', label: null, detail: null, href: null },
        ]);
        expect(wrapper.find('a.ft-affected__link').exists()).toBe(false);
    });

    it('marks checks without a list as unavailable', async () => {
        const error = new Error('Not found');
        error.response = { status: 404 };

        const wrapper = await createWrapper(
            { dataIntegrityAffected: vi.fn().mockRejectedValue(error) },
            createRepositoryFactory([]).factory
        );

        expect(wrapper.vm.unavailable).toBe(true);
        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.text()).toContain(
            'This check cannot list the affected records.'
        );
    });

    it('shows the load error', async () => {
        const wrapper = await createWrapper(
            {
                dataIntegrityAffected: vi
                    .fn()
                    .mockRejectedValue(new Error('Request failed')),
            },
            createRepositoryFactory([]).factory
        );

        expect(wrapper.vm.unavailable).toBe(false);
        expect(wrapper.vm.loadError).toBe('Request failed');
    });

    it('loads the requested page', async () => {
        const service = {
            dataIntegrityAffected: vi.fn().mockResolvedValue({
                entity: 'rule',
                total: 30,
                ids: [],
            }),
        };

        const wrapper = await createWrapper(
            service,
            createRepositoryFactory([]).factory
        );
        await wrapper.vm.onPageChange({ page: 2, limit: 25 });

        expect(service.dataIntegrityAffected).toHaveBeenLastCalledWith(
            'rule-invalid',
            2,
            25
        );
    });

    it('builds labels and routes for the entities the checks report', () => {
        expect(
            entities.product.label({
                name: null,
                translated: { name: 'Shirt' },
            })
        ).toBe('Shirt');
        expect(
            entities.product.detail({
                productNumber: 'SW1',
                options: [{ translated: { name: 'Red' } }, { name: 'XL' }],
            })
        ).toBe('SW1 · Red · XL');
        expect(
            entities.category.detail({
                translated: { breadcrumb: ['Home', 'Clothing', 'Shirts'] },
            })
        ).toBe('Home › Clothing');
        expect(
            entities.customer.label({
                firstName: 'Max',
                lastName: 'Mustermann',
            })
        ).toBe('Max Mustermann');
        expect(entities.media_folder.route('f')).toEqual({
            name: 'sw.media.index',
            params: { folderId: 'f' },
        });
        expect(
            entities.system_config.route('c', {
                configurationKey: 'core.basicInformation.http404Page',
            })
        ).toEqual({ name: 'sw.settings.basic.information.index' });
        expect(
            entities.system_config.route('c', {
                configurationKey: 'core.cms.default_category_cms_page',
            })
        ).toEqual({ name: 'sw.cms.index' });
    });
});
