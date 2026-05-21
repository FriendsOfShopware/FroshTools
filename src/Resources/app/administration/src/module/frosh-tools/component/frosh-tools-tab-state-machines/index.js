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
            mermaid.initialize({
                startOnLoad: false,
                theme: 'default',
            });

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

            this.isLoading = false;
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

            const criteria = new Criteria([stateMachineChangeId]);
            criteria.addAssociation('states');
            criteria.addAssociation('transitions');

            const stateMachine = await this.stateMachineRepository.get(
                stateMachineChangeId,
                Shopware.Context.api,
                criteria
            );

            const container = document.getElementById('state_machine');

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
        },
    },
});
