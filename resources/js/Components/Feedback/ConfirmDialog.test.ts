import { mount } from '@vue/test-utils';
import { beforeAll, describe, expect, it } from 'vitest';
import ConfirmDialog from './ConfirmDialog.vue';

beforeAll(() => {
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', {
        configurable: true,
        value(this: HTMLDialogElement): void {
            this.setAttribute('open', '');
        },
    });

    Object.defineProperty(HTMLDialogElement.prototype, 'close', {
        configurable: true,
        value(this: HTMLDialogElement): void {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        },
    });
});

describe('ConfirmDialog', () => {
    it('expone un nombre y una descripción accesibles', async () => {
        const wrapper = mount(ConfirmDialog, {
            props: {
                open: false,
                title: '¿Eliminar a Pikachu?',
                description: 'Esta acción no se puede deshacer.',
            },
            global: {
                stubs: { Teleport: true },
            },
        });

        await wrapper.setProps({ open: true });

        const dialog = wrapper.get('dialog');
        expect(dialog.attributes('aria-labelledby')).toBe(wrapper.get('h2').attributes('id'));
        expect(dialog.attributes('aria-describedby')).toBe(wrapper.get('p').attributes('id'));
    });

    it('mantiene la confirmación como una decisión explícita', async () => {
        const wrapper = mount(ConfirmDialog, {
            props: {
                open: false,
                title: '¿Eliminar a Pikachu?',
                description: 'Esta acción no se puede deshacer.',
                confirmLabel: 'Eliminar Pokémon',
            },
            global: {
                stubs: { Teleport: true },
            },
        });

        await wrapper.setProps({ open: true });
        await wrapper.get('button:last-child').trigger('click');

        expect(wrapper.emitted('confirm')).toHaveLength(1);
    });
});
