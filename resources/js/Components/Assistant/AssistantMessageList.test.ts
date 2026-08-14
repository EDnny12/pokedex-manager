import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AssistantMessageList from './AssistantMessageList.vue';

const defaultProps = {
    messages: [],
    actions: [],
    loading: false,
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

    it('renderiza mensajes del usuario y de Lía', () => {
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

        expect(wrapper.text()).toContain('Lía verificará el Pokémon en la Pokédex');
        expect(wrapper.emitted('scan')).toHaveLength(1);
    });

    it('anuncia que Lía está consultando la Pokédex', () => {
        const wrapper = mount(AssistantMessageList, {
            props: {
                ...defaultProps,
                sending: true,
                messages: [{ id: '1', role: 'user', content: 'Háblame de Mew.', metadata: {}, attachments: [], created_at: '2026-08-13T00:00:00Z' }],
            },
        });

        expect(wrapper.get('[role="status"]').text()).toContain('Lía está consultando la Pokédex…');
    });
});
