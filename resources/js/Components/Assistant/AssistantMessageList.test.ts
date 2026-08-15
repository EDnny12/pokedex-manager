import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AssistantMessageList from './AssistantMessageList.vue';

const defaultProps = {
    messages: [],
    actions: [],
    loading: false,
    hasOlderMessages: false,
    loadingOlderMessages: false,
    sending: false,
    busyActionId: null,
};

describe('AssistantMessageList', () => {
    it('muestra un estado de carga accesible', () => {
        const wrapper = mount(AssistantMessageList, {
            props: { ...defaultProps, loading: true },
        });

        expect(wrapper.get('[aria-label="Cargando conversación"]')).toBeTruthy();
        expect(wrapper.text()).toContain('Cargando conversación…');
    });

    it('renderiza mensajes del usuario y de Pika IA', () => {
        const wrapper = mount(AssistantMessageList, {
            props: {
                ...defaultProps,
                messages: [
                    { id: '1', role: 'user', content: '¿Qué tipos me faltan?', metadata: {}, attachments: [], created_at: '2026-08-13T00:00:00Z' },
                    { id: '2', role: 'assistant', content: 'Aún no tienes tipo Hielo.', metadata: {}, attachments: [], created_at: '2026-08-13T00:00:01Z' },
                ],
            },
        });

        expect(wrapper.text()).toContain('¿Qué tipos me faltan?');
        expect(wrapper.text()).toContain('Aún no tienes tipo Hielo.');
    });

    it('solicita mensajes anteriores desde un control accesible', async () => {
        const wrapper = mount(AssistantMessageList, {
            props: {
                ...defaultProps,
                hasOlderMessages: true,
                messages: [
                    { id: '1', role: 'assistant', content: 'Mensaje reciente.', metadata: {}, attachments: [], created_at: '2026-08-13T00:00:00Z' },
                ],
            },
        });
        const button = wrapper.get('button');

        expect(button.text()).toBe('Cargar mensajes anteriores');
        await button.trigger('click');

        expect(wrapper.emitted('loadOlder')).toHaveLength(1);
    });

    it('renderiza los adjuntos persistidos con texto alternativo útil', () => {
        const wrapper = mount(AssistantMessageList, {
            props: {
                ...defaultProps,
                messages: [{
                    id: '1',
                    role: 'user',
                    content: '¿Qué Pokémon aparece?',
                    metadata: {},
                    attachments: [{
                        id: 'image-1',
                        name: 'captura.png',
                        mime_type: 'image/png',
                        size: 1200,
                        width: 200,
                        height: 200,
                        url: '/assistant/attachments/image-1',
                    }],
                    created_at: '2026-08-13T00:00:00Z',
                }],
            },
        });

        expect(wrapper.get('img').attributes('src')).toBe('/assistant/attachments/image-1');
        expect(wrapper.get('img').attributes('alt')).toBe('Imagen adjunta: captura.png');
    });

    it('envía una sugerencia inicial como mensaje normal', async () => {
        const wrapper = mount(AssistantMessageList, { props: defaultProps });

        await wrapper.findAll('button').find((button) => button.text() === 'Analiza mi colección')?.trigger('click');

        expect(wrapper.emitted('suggestion')?.[0]).toEqual(['Analiza mi colección']);
    });

    it('ofrece el escáner visual como acción explícita', async () => {
        const wrapper = mount(AssistantMessageList, { props: defaultProps });

        await wrapper.get('[data-testid="assistant-image-scan"]').trigger('click');

        expect(wrapper.text()).toContain('Pika IA verificará el Pokémon en la Pokédex');
        expect(wrapper.emitted('scan')).toHaveLength(1);
    });

    it('anuncia que Pika IA está consultando la Pokédex', () => {
        const wrapper = mount(AssistantMessageList, {
            props: {
                ...defaultProps,
                sending: true,
                messages: [{ id: '1', role: 'user', content: 'Háblame de Mew.', metadata: {}, attachments: [], created_at: '2026-08-13T00:00:00Z' }],
            },
        });

        expect(wrapper.get('[role="status"]').text()).toContain('Pika IA está consultando la Pokédex…');
    });
});
