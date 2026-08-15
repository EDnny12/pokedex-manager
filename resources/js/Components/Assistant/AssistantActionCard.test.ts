import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AssistantActionCard from './AssistantActionCard.vue';

const action = {
    id: '019ffde2-90f0-7388-807d-c5539cfc7b92',
    type: 'remove_pokemon' as const,
    status: 'pending' as const,
    payload: { pokemon_id: 25, display_name: 'Pikachu', image: null },
    expires_at: new Date(Date.now() + 60_000).toISOString(),
    executed_at: null,
};

const addAction = {
    ...action,
    id: '019ffde2-90f0-7388-807d-c5539cfc7b93',
    type: 'add_pokemon' as const,
    payload: { pokemon_id: 151, display_name: 'Mew', image: null },
};

const updateAction = {
    ...action,
    id: '019ffde2-90f0-7388-807d-c5539cfc7b94',
    type: 'update_pokemon' as const,
    payload: {
        pokemon_id: 25,
        display_name: 'Pikachu',
        image: null,
        changes: {
            nickname: 'Chispitas',
            notes: null,
            is_favorite: true,
        },
    },
};

describe('AssistantActionCard', () => {
    it('explica la consecuencia y exige una acción explícita', async () => {
        const wrapper = mount(AssistantActionCard, { props: { action } });

        expect(wrapper.text()).toContain('También se perderán su apodo, notas y estado de favorito.');
        expect(wrapper.text()).toContain('Eliminar Pokémon');

        const confirmButton = wrapper.findAll('button').find((button) => button.text().includes('Eliminar Pokémon'));
        await confirmButton?.trigger('click');

        expect(wrapper.emitted('confirm')?.[0]).toEqual([action]);
    });

    it('bloquea ambas acciones mientras se procesa', () => {
        const wrapper = mount(AssistantActionCard, { props: { action, busy: true } });

        expect(wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined)).toBe(true);
    });

    it('permite confirmar o cancelar una incorporación desde controles estructurados', async () => {
        const wrapper = mount(AssistantActionCard, { props: { action: addAction } });

        expect(wrapper.text()).toContain('Agregar a Mew');
        await wrapper.get('button:last-child').trigger('click');
        await wrapper.get('button:first-child').trigger('click');

        expect(wrapper.emitted('confirm')?.[0]).toEqual([addAction]);
        expect(wrapper.emitted('cancel')?.[0]).toEqual([addAction]);
    });

    it('permite cancelar una eliminación sin ejecutarla', async () => {
        const wrapper = mount(AssistantActionCard, { props: { action } });

        await wrapper.get('button:first-child').trigger('click');

        expect(wrapper.emitted('cancel')?.[0]).toEqual([action]);
        expect(wrapper.emitted('confirm')).toBeUndefined();
    });

    it('resume y confirma únicamente los cambios solicitados', async () => {
        const wrapper = mount(AssistantActionCard, { props: { action: updateAction } });

        expect(wrapper.text()).toContain('Editar datos de Pikachu');
        expect(wrapper.text()).toContain('Apodo: Chispitas');
        expect(wrapper.text()).toContain('Quitar las notas');
        expect(wrapper.text()).toContain('Marcar como favorito');
        expect(wrapper.text()).toContain('Guardar cambios');

        await wrapper.get('button:last-child').trigger('click');

        expect(wrapper.emitted('confirm')?.[0]).toEqual([updateAction]);
    });
});
