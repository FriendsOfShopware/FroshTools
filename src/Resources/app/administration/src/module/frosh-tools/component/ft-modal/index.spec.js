import { mount } from '@vue/test-utils';
import './index';

async function createWrapper({ props = {}, slots = {} } = {}) {
    const component = await Shopware.Component.build('ft-modal');

    return mount(component, {
        props,
        slots: {
            default: '<p>Body content</p>',
            ...slots,
        },
        global: {
            stubs: {
                'ft-icon': true,
                teleport: true,
            },
            mocks: {
                $t: (key) => key,
            },
        },
        attachTo: document.body,
    });
}

describe('ft-modal', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('renders as an accessible dialog labelled by its title', async () => {
        const wrapper = await createWrapper({
            props: { title: 'Confirm action' },
        });

        const dialog = wrapper.find('[role="dialog"]');
        expect(dialog.exists()).toBe(true);
        expect(dialog.attributes('aria-modal')).toBe('true');

        const titleId = dialog.attributes('aria-labelledby');
        expect(titleId).toBeTruthy();
        expect(wrapper.find(`#${titleId}`).text()).toBe('Confirm action');
    });

    it('emits close when Escape is pressed', async () => {
        const wrapper = await createWrapper({
            props: { title: 'Dialog' },
        });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('moves focus into the dialog on open', async () => {
        const wrapper = await createWrapper({
            props: { title: 'Dialog' },
            slots: {
                footer: '<button type="button" class="footer-btn">Save</button>',
            },
        });
        await wrapper.vm.$nextTick();
        await flushPromises();

        const dialog = wrapper.find('[role="dialog"]').element;
        expect(dialog.contains(document.activeElement)).toBe(true);
    });

    it('keeps Tab focus cycling inside the dialog', async () => {
        const wrapper = await createWrapper({
            props: { title: 'Dialog' },
            slots: {
                footer: '<button type="button" class="footer-btn">Save</button>',
            },
        });
        await wrapper.vm.$nextTick();
        await flushPromises();

        const focusable = wrapper.vm.focusableElements();
        expect(focusable.length).toBeGreaterThan(1);

        // Tab on the last element wraps to the first.
        const last = focusable[focusable.length - 1];
        last.focus();
        const event = new KeyboardEvent('keydown', {
            key: 'Tab',
            bubbles: true,
            cancelable: true,
        });
        wrapper.vm.onKeydown(event);

        expect(event.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(focusable[0]);

        // Shift+Tab on the first element wraps to the last.
        const shiftEvent = new KeyboardEvent('keydown', {
            key: 'Tab',
            shiftKey: true,
            bubbles: true,
            cancelable: true,
        });
        wrapper.vm.onKeydown(shiftEvent);

        expect(shiftEvent.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(last);
    });

    it('restores focus to the previously focused element on close', async () => {
        const trigger = document.createElement('button');
        document.body.appendChild(trigger);
        trigger.focus();

        const wrapper = await createWrapper({
            props: { title: 'Dialog' },
        });
        await wrapper.vm.$nextTick();
        await flushPromises();

        expect(document.activeElement).not.toBe(trigger);

        wrapper.unmount();

        expect(document.activeElement).toBe(trigger);
    });

    it('closes on backdrop click only when closeOnBackdrop is enabled', async () => {
        const wrapper = await createWrapper({
            props: { title: 'Dialog' },
        });

        await wrapper.find('.ft-modal').trigger('mousedown');
        expect(wrapper.emitted('close')).toHaveLength(1);

        const persistent = await createWrapper({
            props: { title: 'Dialog', closeOnBackdrop: false },
        });
        await persistent.find('.ft-modal').trigger('mousedown');
        expect(persistent.emitted('close')).toBeUndefined();

        persistent.unmount();
    });
});
