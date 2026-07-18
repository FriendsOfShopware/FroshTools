import { mount } from '@vue/test-utils';
import '../../../../mixin/sortable-table';
import './index';

/**
 * ft-th-sort is always used inside a component using the frosh-sortable-table
 * mixin (which provides itself as froshSortHost), so these specs mount that
 * real combination instead of mocking the host.
 */
async function createWrapper({ sortKey = 'name', table = 'default' } = {}) {
    const thSort = await Shopware.Component.build('ft-th-sort');

    const host = {
        template: `
            <table>
                <thead>
                    <tr>
                        <th-sort sort-key="${sortKey}" table="${table}">Name</th-sort>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>{{ firstRowName }}</td></tr>
                </tbody>
            </table>
        `,
        mixins: [Shopware.Mixin.getByName('frosh-sortable-table')],
        components: { 'th-sort': thSort },
        data() {
            return {
                rows: [{ name: 'charlie' }, { name: 'alpha' }],
            };
        },
        computed: {
            firstRowName() {
                return this.sortRows(this.rows, table)[0].name;
            },
        },
    };

    return mount(host, {
        global: {
            stubs: { 'ft-icon': true },
        },
    });
}

describe('ft-th-sort', () => {
    it('renders a real button inside the table header', async () => {
        const wrapper = await createWrapper();

        const button = wrapper.find('th button.ft-table__sort-btn');
        expect(button.exists()).toBe(true);
        expect(button.attributes('type')).toBe('button');
        expect(button.text()).toContain('Name');
    });

    it('has no aria-sort attribute when the column is not sorted', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('th').attributes('aria-sort')).toBeUndefined();
    });

    it('cycles aria-sort ascending -> descending on repeated clicks', async () => {
        const wrapper = await createWrapper();
        const button = wrapper.find('th button');

        await button.trigger('click');
        expect(wrapper.find('th').attributes('aria-sort')).toBe('ascending');
        expect(wrapper.find('tbody td').text()).toBe('alpha');

        await button.trigger('click');
        expect(wrapper.find('th').attributes('aria-sort')).toBe('descending');
        expect(wrapper.find('tbody td').text()).toBe('charlie');
    });

    it('scopes sorting to the given table', async () => {
        const wrapper = await createWrapper({ table: 'health' });
        const button = wrapper.find('th button');

        await button.trigger('click');

        expect(wrapper.vm.tableSorts.health).toEqual({
            key: 'name',
            dir: 'ASC',
        });
        expect(wrapper.vm.tableSorts.default).toBeUndefined();
    });

    it('works without a sort host', async () => {
        const thSort = await Shopware.Component.build('ft-th-sort');
        const wrapper = mount(thSort, {
            props: { sortKey: 'name' },
            slots: { default: 'Name' },
            global: {
                stubs: { 'ft-icon': true },
            },
        });

        expect(wrapper.find('th').attributes('aria-sort')).toBeUndefined();

        // Clicking must not explode when no host is provided.
        await wrapper.find('button').trigger('click');
        expect(wrapper.vm.dir).toBeNull();
    });
});
