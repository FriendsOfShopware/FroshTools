import { mount } from '@vue/test-utils';
import './sortable-table';

function createWrapper() {
    return mount(
        {
            template: '<div />',
            mixins: [Shopware.Mixin.getByName('frosh-sortable-table')],
        },
        { attachTo: false }
    );
}

describe('frosh-sortable-table mixin', () => {
    it('provides itself as froshSortHost for ft-th-sort children', () => {
        const wrapper = createWrapper();

        const provided = wrapper.vm.$.provides;
        expect(provided.froshSortHost).toBe(wrapper.vm);
    });

    it('toggles sort direction ASC -> DESC -> ASC for the same key', () => {
        const wrapper = createWrapper();

        wrapper.vm.toggleSort('name');
        expect(wrapper.vm.sortDirOf('name')).toBe('ASC');

        wrapper.vm.toggleSort('name');
        expect(wrapper.vm.sortDirOf('name')).toBe('DESC');

        wrapper.vm.toggleSort('name');
        expect(wrapper.vm.sortDirOf('name')).toBe('ASC');
    });

    it('resets direction to ASC when switching sort keys', () => {
        const wrapper = createWrapper();

        wrapper.vm.toggleSort('name');
        wrapper.vm.toggleSort('name');
        expect(wrapper.vm.sortDirOf('name')).toBe('DESC');

        wrapper.vm.toggleSort('size');
        expect(wrapper.vm.sortDirOf('size')).toBe('ASC');
        expect(wrapper.vm.sortDirOf('name')).toBeNull();
    });

    it('keeps sort state isolated per table scope', () => {
        const wrapper = createWrapper();

        wrapper.vm.toggleSort('name', 'health');
        wrapper.vm.toggleSort('size', 'performance');

        expect(wrapper.vm.sortDirOf('name', 'health')).toBe('ASC');
        expect(wrapper.vm.sortDirOf('size', 'performance')).toBe('ASC');
        expect(wrapper.vm.sortDirOf('name', 'performance')).toBeNull();
    });

    it('sorts rows ascending and descending without mutating the source', () => {
        const wrapper = createWrapper();
        const rows = [
            { name: 'charlie', size: 30 },
            { name: 'alpha', size: 10 },
            { name: 'bravo', size: 20 },
        ];

        wrapper.vm.toggleSort('name');
        const asc = wrapper.vm.sortRows(rows);
        expect(asc.map((row) => row.name)).toEqual([
            'alpha',
            'bravo',
            'charlie',
        ]);
        expect(rows[0].name).toBe('charlie');

        wrapper.vm.toggleSort('name');
        const desc = wrapper.vm.sortRows(rows);
        expect(desc.map((row) => row.name)).toEqual([
            'charlie',
            'bravo',
            'alpha',
        ]);
    });

    it('sorts numbers numerically and nulls last', () => {
        const wrapper = createWrapper();
        const rows = [{ size: 100 }, { size: null }, { size: 9 }];

        wrapper.vm.toggleSort('size');
        const sorted = wrapper.vm.sortRows(rows);

        expect(sorted.map((row) => row.size)).toEqual([9, 100, null]);
    });

    it('returns rows untouched when no sort is active', () => {
        const wrapper = createWrapper();
        const rows = [{ name: 'b' }, { name: 'a' }];

        expect(wrapper.vm.sortRows(rows)).toBe(rows);
        expect(wrapper.vm.sortRows(null)).toBeNull();
    });
});
