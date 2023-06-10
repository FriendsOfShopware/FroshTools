import template from './template.twig';
import DiffMatchPatch from "diff-match-patch";

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-files', {
    template,
    inject: ['repositoryFactory', 'froshToolsService'],
    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            items: {},
            isLoading: true,
            diffData: {
                html: '',
                file: ''
            },
            showModal: false,
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'name',
                    label: 'frosh-tools.name',
                    rawData: true,
                    primary: true
                },
                {
                    property: 'expected',
                    label: 'frosh-tools.status',
                    rawData: true,
                    primary: true
                }
            ];
        },

        isLoadingClass() {
            return {
                'is-loading': this.isLoading
            }
        },
    },

    methods: {
        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
        },

        async createdComponent() {
            this.items = (await this.froshToolsService.getShopwareFiles()).data;
            this.isLoading = false;
        },

        openUrl(url) {
            window.open(url, '_blank');
        },

        async diff(file) {
            const fileContents = (await this.froshToolsService.getFileContents(file.name)).data;

            const dmp = new DiffMatchPatch();
            const diff = dmp.diff_main(fileContents.originalContent, fileContents.content);
            dmp.diff_cleanupSemantic(diff);
            this.diffData.html = dmp.diff_prettyHtml(diff)
                .replace(new RegExp('background:#e6ffe6;', 'g'), 'background:#ABF2BC;')
                .replace(new RegExp('background:#ffe6e6;', 'g'), 'background:rgba(255,129,130,0.4);');
            this.diffData.file = file;

            this.openModal();
        },

        async restoreFile(name) {
            this.closeModal();
            this.isLoading = true;
            const response = await this.froshToolsService.restoreShopwareFile(name);

            if (response.data.status) {
                this.createNotificationSuccess({
                    message: response.data.status
                })
            } else {
                this.createNotificationError({
                    message: response.data.error
                })
            }

            await this.refresh();
        },

        openModal() {
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
        },
    }
});
