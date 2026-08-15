import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PokemonImage from './PokemonImage.vue';

describe('PokemonImage', () => {
    const rawGithubUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/25.png';

    it('genera la URL de WebP optimizada para sprites de PokeAPI', () => {
        const wrapper = mount(PokemonImage, {
            props: {
                src: rawGithubUrl,
                alt: 'Pikachu',
            },
        });

        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toContain('wsrv.nl');
        expect(img.attributes('src')).toContain('output=webp');
        expect(img.attributes('loading')).toBe('lazy');
        expect(img.attributes('decoding')).toBe('async');
    });

    it('respeta la propiedad eager para imagenes prioritarias (LCP)', () => {
        const wrapper = mount(PokemonImage, {
            props: {
                src: rawGithubUrl,
                alt: 'Pikachu',
                eager: true,
            },
        });

        const img = wrapper.find('img');
        expect(img.attributes('loading')).toBe('eager');
    });

    it('conmuta a la URL original si la version WebP optimizada falla', async () => {
        const wrapper = mount(PokemonImage, {
            props: {
                src: rawGithubUrl,
                alt: 'Pikachu',
            },
        });

        const img = wrapper.find('img');
        expect(img.attributes('src')).toContain('wsrv.nl');

        // Disparamos el primer error (falla WebP)
        await img.trigger('error');

        // Debe conmutar automáticamente al PNG original
        expect(wrapper.find('img').attributes('src')).toBe(rawGithubUrl);
    });

    it('muestra el placeholder vectorial de Pokeball si la imagen original tambien falla', async () => {
        const wrapper = mount(PokemonImage, {
            props: {
                src: rawGithubUrl,
                alt: 'Pikachu',
            },
        });

        const img = wrapper.find('img');
        // Primer error -> conmuta a raw
        await img.trigger('error');
        // Segundo error -> falla raw
        await img.trigger('error');

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('[role="img"]').exists()).toBe(true);
        expect(wrapper.find('[role="img"]').attributes('aria-label')).toBe('Pikachu');
    });

    it('muestra el placeholder vectorial de Pokeball inmediatamente si src es null', () => {
        const wrapper = mount(PokemonImage, {
            props: {
                src: null,
                alt: 'Pokemon Desconocido',
            },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('[role="img"]').exists()).toBe(true);
    });
});
