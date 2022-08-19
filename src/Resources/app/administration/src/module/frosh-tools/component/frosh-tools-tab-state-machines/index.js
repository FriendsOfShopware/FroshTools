/*global Shopware*/

import './style.scss';
import template from './template.html.twig';

const { Component, Mixin } = Shopware;

Component.register('frosh-tools-tab-state-machines', {
    template,

    inject: ['froshToolsService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            image: null,
            featureFlags: null,
            isLoading: true,
        }
    },

    created() {
        this.createdComponent();
    },

    computed: {
        columns() {
            return [
                {
                    property: 'flag',
                    label: 'frosh-tools.tabs.feature-flags.flag',
                    rawData: true,
                },
                {
                    property: 'active',
                    label: 'frosh-tools.active',
                    rawData: true,
                },
                {
                    property: 'description',
                    label: 'frosh-tools.tabs.feature-flags.description',
                    rawData: true,
                },
                {
                    property: 'major',
                    label: 'frosh-tools.tabs.feature-flags.major',
                    rawData: true,
                },
                {
                    property: 'default',
                    label: 'frosh-tools.tabs.feature-flags.default',
                    rawData: true,
                },
            ];
        },
    },

    methods: {
        createdComponent() {
            this.isLoading = false;
        },

        async onStateMachineChange(event) {
            const response = (await this.froshToolsService.stateMachines(event.srcElement.value));
            const elem = document.getElementById('state_machine'); 
            if ("svg" in response) {
                this.image = response.svg;
                elem.src = this.image;
                elem.style.opacity = '1';
                elem.style.width = '100%';
                elem.style.height = 'auto';
            } else {
                elem.style.opacity = '0';
            }
        }
    }
});
