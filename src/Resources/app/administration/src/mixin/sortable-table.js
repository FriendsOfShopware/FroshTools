const { Mixin } = Shopware;

function resolvePath(obj, path) {
    if (obj == null) return obj;
    return path.split('.').reduce((acc, key) => (acc == null ? acc : acc[key]), obj);
}

function compare(a, b) {
    if (a == null && b == null) return 0;
    if (a == null) return 1;
    if (b == null) return -1;

    if (typeof a === 'number' && typeof b === 'number') {
        return a - b;
    }
    if (typeof a === 'boolean' && typeof b === 'boolean') {
        return (a === b) ? 0 : (a ? 1 : -1);
    }
    return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

Mixin.register('frosh-sortable-table', {
    data() {
        return {
            tableSorts: {},
        };
    },

    methods: {
        toggleSort(key, table = 'default') {
            const current = this.tableSorts[table];
            let dir = 'ASC';
            if (current && current.key === key) {
                dir = current.dir === 'ASC' ? 'DESC' : 'ASC';
            }
            this.tableSorts = {
                ...this.tableSorts,
                [table]: { key, dir },
            };
        },

        sortDirOf(key, table = 'default') {
            const current = this.tableSorts[table];
            if (!current || current.key !== key) return null;
            return current.dir;
        },

        sortRows(rows, table = 'default') {
            if (!Array.isArray(rows)) return rows;
            const current = this.tableSorts[table];
            if (!current || !current.key) return rows;

            const copy = rows.slice();
            copy.sort((a, b) => compare(resolvePath(a, current.key), resolvePath(b, current.key)));
            if (current.dir === 'DESC') copy.reverse();
            return copy;
        },
    },
});
