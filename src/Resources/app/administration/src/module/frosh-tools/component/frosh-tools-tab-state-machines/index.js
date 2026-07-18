import './style.scss';
import template from './template.html.twig';
import mermaid from 'mermaid';

const {
    Component,
    Mixin,
    Data: { Criteria },
} = Shopware;

Component.register('frosh-tools-tab-state-machines', {
    template,

    inject: ['repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            selectedStateMachine: null,
            stateMachineOptions: [],
            isLoading: true,
            loadError: null,
            renderCount: 0,
        };
    },

    computed: {
        stateMachineRepository() {
            return this.repositoryFactory.create('state_machine');
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        async createdComponent() {
            this.isLoading = true;
            this.loadError = null;

            mermaid.initialize({
                startOnLoad: false,
                theme: 'default',
            });

            try {
                const criteria = new Criteria();
                criteria.addSorting(Criteria.sort('name', 'ASC'));

                const stateMachines = await this.stateMachineRepository.search(
                    criteria,
                    Shopware.Context.api
                );
                this.stateMachineOptions = stateMachines.map((sm) => ({
                    value: sm.id,
                    label: sm.name,
                }));
            } catch (error) {
                this.stateMachineOptions = [];
                this.loadError = error?.response?.data?.error ?? error.message;
                this.createNotificationError({ message: this.loadError });
            } finally {
                this.isLoading = false;
            }
        },

        buildMermaidDiagram(stateMachine) {
            const states = stateMachine.states;
            const transitions = stateMachine.transitions;
            const initialState = stateMachine.initialState;

            const lines = ['flowchart TD'];

            if (initialState) {
                lines.push(`START_STATE[Start state] --> ${initialState.id}`);
            }

            states.forEach((state) => {
                const name = state.name.replace(/[()]/g, '');
                lines.push(`${state.id}(${name})`);

                const hasOutgoing = transitions.some(
                    (t) =>
                        t.fromStateId === state.id && t.actionName !== 'reopen'
                );

                if (!hasOutgoing) {
                    lines.push(`${state.id} --> FINAL_STATE[Final state]`);
                }
            });

            transitions.forEach((transition) => {
                lines.push(
                    `${transition.fromStateId} -- ${transition.actionName} --> ${transition.toStateId}`
                );
            });

            return lines.join('\n');
        },

        async onStateMachineChange(stateMachineChangeId) {
            if (!stateMachineChangeId) {
                return;
            }

            const container = document.getElementById('state_machine');

            try {
                const criteria = new Criteria([stateMachineChangeId]);
                criteria.addAssociation('states');
                criteria.addAssociation('transitions');

                const stateMachine = await this.stateMachineRepository.get(
                    stateMachineChangeId,
                    Shopware.Context.api,
                    criteria
                );

                if (!stateMachine) {
                    container.innerHTML = '';
                    return;
                }

                const diagram = this.buildMermaidDiagram(stateMachine);

                this.renderCount += 1;
                const { svg } = await mermaid.render(
                    `mermaid-diagram-${this.renderCount}`,
                    diagram
                );
                container.innerHTML = svg;
            } catch (error) {
                container.innerHTML = '';
                this.createNotificationError({
                    message: error?.response?.data?.error ?? error.message,
                });
            }
        },
    },
});
