import { mount } from '@vue/test-utils';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import AssistantComposer from './AssistantComposer.vue';

describe('AssistantComposer', () => {
    beforeAll(() => {
        Object.defineProperty(URL, 'createObjectURL', { value: vi.fn(() => 'blob:preview') });
        Object.defineProperty(URL, 'revokeObjectURL', { value: vi.fn() });
    });

    it('emite el mensaje limpio y espera la confirmación del envío para vaciarlo', async () => {
        const wrapper = mount(AssistantComposer, {
            props: { sending: false, modelValue: '', images: [] },
        });
        const textarea = wrapper.get('textarea');

        await textarea.setValue('  Resume mi colección  ');
        await wrapper.get('form').trigger('submit');

        expect(wrapper.emitted('submit')?.[0]).toEqual(['Resume mi colección', []]);
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['  Resume mi colección  ']);
    });

    it('deshabilita el envío mientras Pika IA responde', () => {
        const wrapper = mount(AssistantComposer, {
            props: { sending: true, modelValue: 'Hola', images: [] },
        });

        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('textarea').attributes('disabled')).toBeDefined();
    });

    it('acepta una imagen válida y permite quitarla de la vista previa', async () => {
        const image = new File(['imagen'], 'pikachu.png', { type: 'image/png' });
        const wrapper = mount(AssistantComposer, {
            props: { sending: false, modelValue: '', images: [] },
        });
        const input = wrapper.get<HTMLInputElement>('input[type="file"]');
        Object.defineProperty(input.element, 'files', { configurable: true, value: [image] });

        await input.trigger('change');
        expect(wrapper.emitted('update:images')?.[0]).toEqual([[image]]);

        await wrapper.setProps({ images: [image] });
        expect(wrapper.get('img').attributes('alt')).toBe('Vista previa de pikachu.png');

        await wrapper.get('button[aria-label="Quitar pikachu.png"]').trigger('click');
        expect(wrapper.emitted('update:images')?.at(-1)).toEqual([[]]);
    });

    it('expone el selector de imágenes para iniciar el escáner visual', () => {
        const wrapper = mount(AssistantComposer, {
            props: { sending: false, modelValue: '', images: [] },
        });
        const input = wrapper.get<HTMLInputElement>('input[type="file"]');
        const click = vi.spyOn(input.element, 'click');

        (wrapper.vm as unknown as { openImagePicker: () => void }).openImagePicker();

        expect(click).toHaveBeenCalledOnce();
    });
});
