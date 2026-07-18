import template from './template.html.twig';

const { Component } = Shopware;

Component.register('ft-th-sort', {
    template,

    inject: {
        froshSortHost: { default: null },
    },

    props: {
        sortKey: {
            type: String,
            required: true,
        },
        table: {
            type: String,
            default: 'default',
        },
    },

    computed: {
        dir() {
            const host = this.froshSortHost;
            if (!host || typeof host.sortDirOf !== 'function') return null;
            return host.sortDirOf(this.sortKey, this.table);
        },

        ariaSort() {
            if (!this.dir) return null;
            return this.dir === 'ASC' ? 'ascending' : 'descending';
        },
    },

    methods: {
        onClick() {
            const host = this.froshSortHost;
            if (host && typeof host.toggleSort === 'function') {
                host.toggleSort(this.sortKey, this.table);
            }
        },
    },
});
