import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import PokemonDetail from './PokemonDetail.vue';

describe('PokemonDetail', () => {
    const mockPokemon = {
        id: 25,
        name: 'pikachu',
        display_name: 'Pikachu',
        image: 'https://example.com/pikachu.png',
        cry_url: 'https://example.com/pikachu.ogg',
        types: ['electric'],
        height_m: 0.4,
        weight_kg: 6.0,
        abilities: [{ name: 'Static', is_hidden: false }],
        stats: { hp: 35, attack: 55, defense: 40, 'special-attack': 50, 'special-defense': 50, speed: 90 },
    };

    let playMock: ReturnType<typeof vi.fn>;
    let pauseMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        playMock = vi.fn().mockResolvedValue(undefined);
        pauseMock = vi.fn();
        class MockAudio {
            play = playMock;
            pause = pauseMock;
            currentTime = 0;
            onended: (() => void) | null = null;
            onerror: (() => void) | null = null;
            constructor(public src?: string) {}
        }
        window.Audio = MockAudio as unknown as typeof Audio;
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('renderiza la información del Pokémon correctamente', () => {
        const wrapper = mount(PokemonDetail, {
            props: { pokemon: mockPokemon },
        });

        expect(wrapper.text()).toContain('Pikachu');
        expect(wrapper.text()).toContain('#025');
        expect(wrapper.text()).toContain('0.4 m');
        expect(wrapper.text()).toContain('6 kg');
        expect(wrapper.text()).toContain('Static');
    });

    it('muestra el botón de audio cuando cry_url está presente y reproduce el sonido al hacer clic', async () => {
        const wrapper = mount(PokemonDetail, {
            props: { pokemon: mockPokemon },
        });

        const audioButton = wrapper.find('button[aria-label="Escuchar sonido característico de Pikachu"]');
        expect(audioButton.exists()).toBe(true);

        await audioButton.trigger('click');
        expect(playMock).toHaveBeenCalledOnce();
    });

    it('no muestra el botón de audio si no hay fuente de sonido', () => {
        const wrapper = mount(PokemonDetail, {
            props: {
                pokemon: { ...mockPokemon, id: 0, cry_url: null },
            },
        });

        const audioButton = wrapper.find('button[aria-label="Escuchar sonido característico de Pikachu"]');
        expect(audioButton.exists()).toBe(false);
    });
});
