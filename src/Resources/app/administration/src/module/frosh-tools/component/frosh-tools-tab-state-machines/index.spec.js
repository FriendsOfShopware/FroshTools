import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers';

vi.mock('mermaid', () => ({
    default: {
        initialize: vi.fn(),
        render: vi
            .fn()
            .mockResolvedValue({ svg: '<svg data-testid="diagram"></svg>' }),
    },
}));

import './index';

const MACHINES = [
    { id: 'order', name: 'Order state' },
    { id: 'payment', name: 'Payment state' },
];

const ORDER_MACHINE = {
    id: 'order',
    initialState: { id: 'open' },
    states: [
        { id: 'open', name: 'Open' },
        { id: 'paid', name: 'Paid' },
    ],
    transitions: [
        { fromStateId: 'open', toStateId: 'paid', actionName: 'pay' },
    ],
};

function createRepository() {
    return {
        search: vi.fn().mockResolvedValue(MACHINES),
        get: vi.fn().mockResolvedValue(ORDER_MACHINE),
    };
}

async function createWrapper(repository = createRepository()) {
    return mountRegistered('frosh-tools-tab-state-machines', {
        provide: {
            repositoryFactory: { create: () => repository },
        },
        stubs: { 'sw-single-select': true },
        attachTo: document.body,
    });
}

describe('frosh-tools-tab-state-machines', () => {
    beforeEach(() => {
        document.body.innerHTML = '<div id="state_machine"></div>';
    });

    it('loads state machine options', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.stateMachineOptions).toEqual([
            { value: 'order', label: 'Order state' },
            { value: 'payment', label: 'Payment state' },
        ]);
    });

    it('builds a mermaid flowchart from states and transitions', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const diagram = wrapper.vm.buildMermaidDiagram(ORDER_MACHINE);

        expect(diagram).toContain('flowchart TD');
        expect(diagram).toContain('START_STATE[Start state] --> open');
        expect(diagram).toContain('open(Open)');
        expect(diagram).toContain('paid --> FINAL_STATE[Final state]');
        expect(diagram).toContain('open -- pay --> paid');
    });

    it('renders the selected state machine into the diagram container', async () => {
        const mermaid = (await import('mermaid')).default;
        const repository = createRepository();
        const wrapper = await createWrapper(repository);
        await flushPromises();

        await wrapper.vm.onStateMachineChange('order');
        await flushPromises();

        expect(repository.get).toHaveBeenCalled();
        expect(mermaid.render).toHaveBeenCalled();
        expect(document.getElementById('state_machine').innerHTML).toContain(
            'data-testid="diagram"'
        );
    });
});
