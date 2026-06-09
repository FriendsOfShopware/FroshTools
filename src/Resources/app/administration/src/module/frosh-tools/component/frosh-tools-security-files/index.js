import template from './template.twig';
import './style.scss';
import DiffMatchPatch from 'diff-match-patch';

const { Component, Mixin } = Shopware;

// File integrity: detects drift between shipped Shopware/extension files and
// what is on disk, with a diff modal and a guarded restore action. Interactive,
// so it lives on its own sub-tab inside the Security Center.
Component.register('frosh-tools-security-files', {
    template,
    inject: ['froshToolsService'],
    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('frosh-sortable-table'),
    ],

    data() {
        return {
            items: {},
            extensionItems: {},
            isLoading: true,
            diffData: {
                html: '',
                file: '',
            },
            showModal: false,
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async refresh() {
            this.isLoading = true;
            await this.createdComponent();
        },

        async createdComponent() {
            this.items = (await this.froshToolsService.getShopwareFiles()).data;
            this.extensionItems = (
                await this.froshToolsService.getExtensionFiles()
            ).data;
            this.isLoading = false;
        },

        openUrl(url) {
            window.open(url, '_blank');
        },

        async diff(file) {
            this.isLoading = true;
            const fileContents = (
                await this.froshToolsService.getFileContents(file.name)
            ).data;

            const dmp = new DiffMatchPatch();
            const diff = dmp.diff_main(
                fileContents.originalContent,
                fileContents.content
            );
            dmp.diff_cleanupSemantic(diff);
            this.diffData.html = dmp
                .diff_prettyHtml(diff)
                .replace(
                    new RegExp('background:#e6ffe6;', 'g'),
                    'background:#ABF2BC;'
                )
                .replace(
                    new RegExp('background:#ffe6e6;', 'g'),
                    'background:rgba(255,129,130,0.4);'
                );
            this.diffData.file = file;

            this.openModal();
            this.isLoading = false;
        },

        async restoreFile(name) {
            this.closeModal();
            this.isLoading = true;
            const response =
                await this.froshToolsService.restoreShopwareFile(name);

            if (response.data.status) {
                this.createNotificationSuccess({
                    message: response.data.status,
                });
            } else {
                this.createNotificationError({
                    message: response.data.error,
                });
            }

            await this.refresh();
        },

        openModal() {
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
        },
    },
});
