import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { CollectionImpact } from '@/types/pokemon';
import CollectionImpactPreview from './CollectionImpactPreview.vue';

function impact(overrides: Partial<CollectionImpact> = {}): CollectionImpact {
    return {
        mode: 'add',
        status: 'expands',
        is_partial: false,
        total: { before: 2, after: 3 },
        represented_types: { before: 3, after: 4 },
        new_types: ['electric'],
        reinforced_types: [],
        lost_types: [],
        stat_changes: [{ key: 'speed', label: 'Velocidad máxima', before: 45, after: 90 }],
        ...overrides,
    };
}

describe('CollectionImpactPreview', () => {
    it('explica qué cambia antes de agregar un Pokémon', () => {
        const wrapper = mount(CollectionImpactPreview, {
            props: { impact: impact() },
        });

        expect(wrapper.get('[data-testid="collection-impact"]').attributes('aria-labelledby')).toBeTruthy();
        expect(wrapper.text()).toContain('Amplía tu colección');
        expect(wrapper.text()).toContain('Tipos nuevos en tu colección');
        expect(wrapper.text()).toContain('Eléctrico');
        expect(wrapper.text()).toContain('Velocidad máxima');
        expect(wrapper.text()).toContain('45');
        expect(wrapper.text()).toContain('90');
    });

    it('advierte las pérdidas antes de eliminar un Pokémon', () => {
        const wrapper = mount(CollectionImpactPreview, {
            props: {
                compact: true,
                impact: impact({
                    mode: 'remove',
                    status: 'empties_collection',
                    total: { before: 1, after: 0 },
                    represented_types: { before: 1, after: 0 },
                    new_types: [],
                    lost_types: ['electric'],
                    stat_changes: [],
                }),
            },
        });

        expect(wrapper.text()).toContain('Tu colección quedará vacía');
        expect(wrapper.text()).toContain('Tipos que dejarán de estar representados');
        expect(wrapper.text()).not.toContain('Estimación basada');
    });
});
