import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import TrainerCard from './TrainerCard.vue';
import type { TrainerCardData } from '@/types/pokemon';

vi.mock('../../../../vendor/tightenco/ziggy', () => ({
    route: vi.fn((name?: string) => (name ? `/${name.replace('.', '/')}` : { current: () => false })),
}));

describe('TrainerCard', () => {
    const mockCard: TrainerCardData = {
        trainer_number: 'TR-00001',
        trainer_name: 'Red',
        avatar_url: 'https://example.com/avatar.jpg',
        member_since: '15 Ago 2026',
        rank: 'Experto',
        total_pokemon: 18,
        favorites_count: 4,
        dominant_type: 'fire',
        signature_pokemon: {
            id: 6,
            name: 'charizard',
            display_name: 'Charizard',
            image: 'https://example.com/charizard.png',
            types: ['fire', 'flying'],
            height_m: 1.7,
            weight_kg: 90.5,
            abilities: [],
            stats: { hp: 78, speed: 100 },
        },
        party: [
            {
                id: 6,
                name: 'charizard',
                display_name: 'Charizard',
                image: 'https://example.com/charizard.png',
                types: ['fire', 'flying'],
                height_m: 1.7,
                weight_kg: 90.5,
                abilities: [],
                stats: { hp: 78, speed: 100 },
            },
            {
                id: 25,
                name: 'pikachu',
                display_name: 'Pikachu',
                image: 'https://example.com/pikachu.png',
                types: ['electric'],
                height_m: 0.4,
                weight_kg: 6.0,
                abilities: [],
                stats: { hp: 35, speed: 90 },
            },
        ],
        headline: 'Domador de las Llamas',
        description: 'Especialista en Pokémon de tipo Fuego con Charizard como insignia.',
        is_ai_generated: true,
    };

    it('renderiza la información del carné de entrenador correctamente', () => {
        const wrapper = mount(TrainerCard, {
            props: { card: mockCard },
        });

        expect(wrapper.text()).toContain('TR-00001');
        expect(wrapper.text()).toContain('Red');
        expect(wrapper.text()).toContain('Experto');
        expect(wrapper.text()).toContain('18');
        expect(wrapper.text()).toContain('4');
        expect(wrapper.text()).toContain('Domador de las Llamas');
        expect(wrapper.text()).toContain('Charizard');
        expect(wrapper.text()).toContain('Pikachu');
        expect(wrapper.text()).toContain('INSIGNIA');
    });

    it('renderiza placeholders para los slots vacíos del equipo hasta completar 6', () => {
        const wrapper = mount(TrainerCard, {
            props: { card: mockCard },
        });

        // 2 active pokemon, so 4 empty slots
        expect(wrapper.text()).toContain('Slot 3');
        expect(wrapper.text()).toContain('Slot 4');
        expect(wrapper.text()).toContain('Slot 5');
        expect(wrapper.text()).toContain('Slot 6');
    });

    it('renderiza el estado inicial cuando no hay Pokémon en la colección', () => {
        const emptyCard: TrainerCardData = {
            ...mockCard,
            rank: 'Novato',
            total_pokemon: 0,
            favorites_count: 0,
            dominant_type: null,
            signature_pokemon: null,
            party: [],
            headline: 'Entrenador en formación',
            description: 'Aún no has registrado Pokémon en tu colección.',
            is_ai_generated: false,
        };

        const wrapper = mount(TrainerCard, {
            props: { card: emptyCard },
        });

        expect(wrapper.text()).toContain('Novato');
        expect(wrapper.text()).toContain('Por descubrir');
        expect(wrapper.text()).toContain('¡Tu equipo está esperando!');
        expect(wrapper.text()).toContain('Slot 1');
    });
});
