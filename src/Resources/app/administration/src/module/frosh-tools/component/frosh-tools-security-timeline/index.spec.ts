import { afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import './index';
import { reactive } from 'vue';

const entry = {
    id: 'event',
    action: 'user:login',
    createdAt: '2026-10-04T10:00:00+00:00',
    level: 200,
    context: { loginUsername: 'admin', clientIp: '192.0.2.10' },
};

let wrapper: Awaited<ReturnType<typeof mountRegistered>>;
async function mount(
    service = {
        getSecurityActivity: vi.fn().mockResolvedValue({ entries: [entry], total: 30 }),
    },
    route = {},
) {
    const optionsService = {
        getSecurityActivityOptions: vi.fn().mockImplementation((field) =>
            Promise.resolve({
                values: field === 'action' ? ['user:login', 'user:login_failed'] : ['admin', 'alice'],
                hasMore: false,
            }),
        ),
        ...service,
    };
    wrapper = await mountRegistered('frosh-tools-security-timeline', {
        provide: { froshToolsService: optionsService },
        global: {
            mocks: {
                $route: route,
                $router: { replace: vi.fn().mockResolvedValue(undefined) },
            },
        },
        stubs: {
            'sw-pagination': true,
            'frosh-tools-security-activity-select': {
                name: 'frosh-tools-security-activity-select',
                props: ['value', 'options'],
                template: '<div />',
            },
        },
    });
    await flushPromises();
    return optionsService;
}

afterEach(() => {
    wrapper?.unmount();
    vi.useRealTimers();
});

describe('security timeline', () => {
    it('loads the linked subject, follows subject navigation and clears identity scope on reset', async () => {
        const id = 'a'.repeat(32);
        const route = reactive({
            query: { section: 'timeline', subjectType: 'users', subjectId: id },
        });
        const service = await mount(undefined, route);
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({ subjectType: 'users', subjectId: id }),
        );
        expect(wrapper.text()).toContain(id);
        route.query.subjectType = 'integrations';
        route.query.subjectId = 'b'.repeat(32);
        await flushPromises();
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({
                subjectType: 'integrations',
                subjectId: 'b'.repeat(32),
            }),
        );
        wrapper.vm.resetFilters();
        await flushPromises();
        expect(wrapper.vm.$router.replace).toHaveBeenLastCalledWith({
            query: { section: 'timeline' },
        });
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({
                subjectType: '',
                subjectId: '',
                userId: '',
            }),
        );
    });
    it('downloads all applied filters without pagination and releases the object URL', async () => {
        const exportSecurityActivity = vi.fn().mockResolvedValue({ data: new Blob(['{"entries":[]}']) });
        await mount({
            getSecurityActivity: vi.fn().mockResolvedValue({ entries: [], total: 0 }),
            exportSecurityActivity,
        });
        const create = vi.fn().mockReturnValue('blob:activity');
        const revoke = vi.fn();
        Object.defineProperty(window.URL, 'createObjectURL', {
            value: create,
            configurable: true,
        });
        Object.defineProperty(window.URL, 'revokeObjectURL', {
            value: revoke,
            configurable: true,
        });
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
        await wrapper.setData({
            filters: {
                action: 'user:update',
                actor: '',
                from: '',
                to: '',
                userId: '',
                subjectType: 'users',
                subjectId: 'a'.repeat(32),
                clientIp: '192.0.2.1',
            },
            page: 3,
        });
        await wrapper.vm.exportActivity();
        expect(exportSecurityActivity).toHaveBeenCalledWith({
            action: 'user:update',
            actor: '',
            from: '',
            to: '',
            userId: '',
            subjectType: 'users',
            subjectId: 'a'.repeat(32),
            clientIp: '192.0.2.1',
            exact: true,
        });
        expect(click).toHaveBeenCalledOnce();
        expect(revoke).toHaveBeenCalledWith('blob:activity');
        expect(document.querySelector('a[download="security-activity.json"]')).toBeNull();
        click.mockRestore();
        exportSecurityActivity.mockRejectedValueOnce(new Error('offline'));
        await wrapper.vm.exportActivity();
        expect(wrapper.vm.exportError).toBe(true);
        expect(wrapper.vm.isExporting).toBe(false);
    });

    it('loads existing activity, shows the actor and opens safe details', async () => {
        const service = await mount();
        expect(service.getSecurityActivity).toHaveBeenCalledWith({
            exact: true,
            clientIp: '',
            userId: '',
            subjectType: '',
            subjectId: '',
            actor: '',
            action: '',
            from: '',
            to: '',
            page: 1,
            limit: 25,
        });
        expect(wrapper.text()).toContain('admin');
        expect(wrapper.text()).toContain('192.0.2.10');
        expect(wrapper.text()).toContain('Signed in');
        expect(
            wrapper
                .findAllComponents({
                    name: 'frosh-tools-security-activity-select',
                })[1]
                .props('options'),
        ).toEqual([
            { value: 'user:login', label: 'Signed in' },
            { value: 'user:login_failed', label: 'Sign-in failed' },
        ]);
        await wrapper.get('button.ft-link').trigger('click');
        expect(wrapper.get('[role="dialog"]').text()).toContain('loginUsername');
    });

    it('applies filters from page one and keeps them during pagination', async () => {
        const service = await mount();
        wrapper
            .findAllComponents({
                name: 'frosh-tools-security-activity-select',
            })[0]
            .vm.$emit('update:value', 'alice');
        wrapper
            .findAllComponents({
                name: 'frosh-tools-security-activity-select',
            })[1]
            .vm.$emit('update:value', 'user:login');
        await wrapper.get('input[name="from"]').setValue('2026-10-01');
        await wrapper.get('input[name="to"]').setValue('2026-10-04');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith({
            exact: true,
            clientIp: '',
            userId: '',
            subjectType: '',
            subjectId: '',
            actor: 'alice',
            action: 'user:login',
            from: '2026-10-01',
            to: '2026-10-04',
            page: 1,
            limit: 25,
        });
        wrapper.findComponent({ name: 'sw-pagination' }).vm.$emit('page-change', { page: 2, limit: 25 });
        await flushPromises();
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(expect.objectContaining({ actor: 'alice', page: 2 }));
        await wrapper.get('form').trigger('submit');
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(expect.objectContaining({ page: 1 }));
    });

    it('shows errors and allows retry instead of presenting an empty history', async () => {
        const service = await mount({
            getSecurityActivity: vi.fn().mockRejectedValue(new Error('offline')),
        });
        expect(wrapper.findComponent({ name: 'ft-hero-state' }).exists()).toBe(true);
        service.getSecurityActivity.mockResolvedValue({
            entries: [],
            total: 0,
        });
        wrapper.findComponent({ name: 'ft-refresh-button' }).vm.$emit('click');
        await flushPromises();
        expect(wrapper.findComponent({ name: 'ft-hero-state' }).exists()).toBe(false);
        expect(wrapper.findComponent({ name: 'ft-empty' }).exists()).toBe(true);
    });

    it('searches recorded actors and clears the filter selection', async () => {
        const service = await mount();
        const actorSelect = wrapper.findAllComponents({
            name: 'frosh-tools-security-activity-select',
        })[0];
        actorSelect.vm.$emit('search', 'ali');
        await flushPromises();
        expect(service.getSecurityActivityOptions).toHaveBeenLastCalledWith('actor', 'ali', 1);
        actorSelect.vm.$emit('update:value', 'alice');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        await wrapper.get('form .ft-btn[type="button"]').trigger('click');
        await flushPromises();
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({
                actor: '',
                action: '',
                from: '',
                to: '',
                page: 1,
                exact: true,
                clientIp: '',
                userId: '',
                subjectType: '',
                subjectId: '',
            }),
        );
        expect(actorSelect.props('value')).toBeNull();
    });

    it('reports option-loading failures and recovers on retry', async () => {
        const service = await mount();
        service.getSecurityActivityOptions.mockRejectedValueOnce(new Error('offline'));
        wrapper
            .findAllComponents({
                name: 'frosh-tools-security-activity-select',
            })[0]
            .vm.$emit('search', 'ali');
        await flushPromises();
        expect(wrapper.get('[role="alert"]').text()).toContain('Filter choices could not be loaded');
        await wrapper.get('[role="alert"] button').trigger('click');
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('loads additional actions without losing previously loaded choices', async () => {
        const service = await mount();
        service.getSecurityActivityOptions.mockImplementation((field, term, page) =>
            Promise.resolve({
                values: field === 'actor' ? [] : page === 1 ? ['user:login'] : ['plugin:install'],
                hasMore: field === 'action' && page === 1,
            }),
        );
        await wrapper.get('form .ft-btn[type="button"]').trigger('click');
        await flushPromises();
        wrapper
            .findAllComponents({
                name: 'frosh-tools-security-activity-select',
            })[1]
            .vm.$emit('paginate');
        await flushPromises();
        expect(service.getSecurityActivityOptions).toHaveBeenLastCalledWith('action', '', 2);
        expect(
            wrapper
                .findAllComponents({
                    name: 'frosh-tools-security-activity-select',
                })[1]
                .props('options'),
        ).toEqual([
            { value: 'user:login', label: 'Signed in' },
            { value: 'plugin:install', label: 'Plugin installed' },
        ]);
    });

    it('clears the date range back to all time', async () => {
        const service = await mount();
        await wrapper.get('input[name="from"]').setValue('2026-10-01');
        await wrapper.get('input[name="to"]').setValue('2026-10-04');
        const period = wrapper.findAllComponents({
            name: 'frosh-tools-security-activity-select',
        })[2];
        period.vm.$emit('update:value', null);
        await flushPromises();
        expect(period.props('value')).toBe('all');
        expect(wrapper.get('input[name="from"]').element.value).toBe('');
        expect(wrapper.get('input[name="to"]').element.value).toBe('');
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(expect.objectContaining({ from: '', to: '', page: 1 }));
    });

    it('applies UTC presets across month boundaries and accepts a custom range', async () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-03-01T00:30:00Z'));
        const service = await mount();
        const period = wrapper.findAllComponents({
            name: 'frosh-tools-security-activity-select',
        })[2];
        period.vm.$emit('update:value', '7days');
        await flushPromises();
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({
                from: '2026-02-23',
                to: '2026-03-01',
                page: 1,
            }),
        );
        period.vm.$emit('update:value', '30days');
        await flushPromises();
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({ from: '2026-01-31', to: '2026-03-01' }),
        );
        period.vm.$emit('update:value', 'today');
        await flushPromises();
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({ from: '2026-03-01', to: '2026-03-01' }),
        );
        await wrapper.get('input[name="from"]').setValue('2026-02-01');
        expect(period.props('value')).toBe('custom');
        await wrapper.get('input[name="clientIp"]').setValue('192.0.2.1');
        await wrapper.get('form').trigger('submit');
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({
                clientIp: '192.0.2.1',
                from: '2026-02-01',
            }),
        );
        await wrapper.get('form .ft-btn[type="button"]').trigger('click');
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({ clientIp: '', from: '', to: '' }),
        );
    });

    it('opens a user deep link and clears its ID when another actor is chosen', async () => {
        const userId = 'a'.repeat(32);
        const service = await mount(undefined, { query: { userId } });
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(expect.objectContaining({ userId }));
        expect(wrapper.text()).toContain(userId);
        wrapper
            .findAllComponents({
                name: 'frosh-tools-security-activity-select',
            })[0]
            .vm.$emit('update:value', 'alice');
        await wrapper.get('form').trigger('submit');
        expect(service.getSecurityActivity).toHaveBeenLastCalledWith(
            expect.objectContaining({ userId: '', actor: 'alice' }),
        );
    });

    it('ignores stale responses when the filters change', async () => {
        let resolveOld: (value: unknown) => void = () => {};
        const service = {
            getSecurityActivity: vi
                .fn()
                .mockImplementationOnce(
                    () =>
                        new Promise((resolve) => {
                            resolveOld = resolve;
                        }),
                )
                .mockResolvedValue({ entries: [], total: 0 }),
        };
        await mount(service);
        wrapper
            .findAllComponents({
                name: 'frosh-tools-security-activity-select',
            })[0]
            .vm.$emit('update:value', 'nobody');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        resolveOld({ entries: [entry], total: 1 });
        await flushPromises();
        expect(wrapper.find('table').exists()).toBe(false);
    });
});
