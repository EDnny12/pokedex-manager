<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import PokemonTypeBadge from '@/Components/Pokemon/PokemonTypeBadge.vue';
import type { CollectionImpact } from '@/types/pokemon';
import { computed, useId } from 'vue';

const props = withDefaults(defineProps<{
    impact: CollectionImpact;
    compact?: boolean;
}>(), {
    compact: false,
});

const headingId = `collection-impact-${useId()}`;
const isRemoval = computed(() => props.impact.mode === 'remove');
const statusCopy = computed(() => ({
    starts_collection: {
        title: 'Empieza tu colección',
        description: 'Será tu primer Pokémon y añadirá sus tipos a tu colección.',
    },
    expands: {
        title: 'Amplía tu colección',
        description: 'Aporta nuevos tipos o mejora una de tus estadísticas máximas.',
    },
    reinforces: {
        title: 'Refuerza lo que ya tienes',
        description: 'Sus tipos ya están representados y no cambia tus máximos actuales.',
    },
    empties_collection: {
        title: 'Tu colección quedará vacía',
        description: 'Perderás todos los tipos y estadísticas representados actualmente.',
    },
    reduces: {
        title: 'Reduce tu diversidad actual',
        description: 'Perderás al menos un tipo representado o una estadística máxima.',
    },
    keeps_coverage: {
        title: 'Mantiene la diversidad principal',
        description: 'Tus tipos representados y estadísticas máximas no cambiarán.',
    },
}[props.impact.status]));
const relevantTypes = computed(() => {
    if (isRemoval.value) {
        return props.impact.lost_types;
    }

    return props.impact.new_types.length > 0
        ? props.impact.new_types
        : props.impact.reinforced_types;
});
const typesLabel = computed(() => {
    if (isRemoval.value) {
        return 'Tipos que dejarán de estar representados';
    }

    return props.impact.new_types.length > 0
        ? 'Tipos nuevos en tu colección'
        : 'Tipos que reforzará';
});
</script>

<template>
    <section
        :aria-labelledby="headingId"
        class="flex flex-col border border-line bg-surface dark:border-white/10 dark:bg-[#161f2e]"
        :class="compact ? 'gap-4 rounded-2xl p-4' : 'gap-6 rounded-[1.75rem] p-5 sm:p-7'"
        data-testid="collection-impact"
    >
        <div class="flex items-start gap-3">
            <span
                class="grid size-11 shrink-0 place-items-center rounded-xl"
                :class="isRemoval ? 'bg-red-50 text-[#b42534] dark:bg-[#c62f3d]/15 dark:text-[#f3a0a8]' : 'bg-[#172033] text-white dark:bg-[#f7f4ed] dark:text-[#172033]'"
            >
                <AppIcon :name="isRemoval ? 'trash' : 'insights'" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.16em] text-[#9d3340] dark:text-[#f08f99]">Vista previa</p>
                <h2 :id="headingId" class="mt-1 font-bold tracking-[-0.025em]" :class="compact ? 'text-base' : 'text-xl'">{{ statusCopy.title }}</h2>
                <p class="mt-1 text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">{{ statusCopy.description }}</p>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-surface-subtle p-3 dark:bg-[#111927]">
                <dt class="font-mono text-[0.62rem] font-bold uppercase tracking-[0.12em] text-[#7b8392] dark:text-[#9aa5b5]">Pokémon</dt>
                <dd class="mt-1 flex items-baseline gap-2 font-mono text-lg font-bold tabular-nums">
                    <span class="text-[#7b8392] dark:text-[#9aa5b5]">{{ impact.total.before }}</span>
                    <span aria-hidden="true">→</span><span class="sr-only">pasa a</span>
                    <span>{{ impact.total.after }}</span>
                </dd>
            </div>
            <div class="rounded-xl bg-surface-subtle p-3 dark:bg-[#111927]">
                <dt class="font-mono text-[0.62rem] font-bold uppercase tracking-[0.12em] text-[#7b8392] dark:text-[#9aa5b5]">Tipos</dt>
                <dd class="mt-1 flex items-baseline gap-2 font-mono text-lg font-bold tabular-nums">
                    <span class="text-[#7b8392] dark:text-[#9aa5b5]">{{ impact.represented_types.before }}</span>
                    <span aria-hidden="true">→</span><span class="sr-only">pasa a</span>
                    <span>{{ impact.represented_types.after }}</span>
                </dd>
            </div>
        </dl>

        <div v-if="relevantTypes.length > 0" class="flex flex-col gap-2.5">
            <h3 class="text-xs font-bold text-[#596273] dark:text-[#b8c1cf]">{{ typesLabel }}</h3>
            <div class="flex flex-wrap gap-2">
                <PokemonTypeBadge v-for="type in relevantTypes" :key="type" :type="type" />
            </div>
        </div>

        <div v-if="impact.stat_changes.length > 0" class="flex flex-col gap-2.5">
            <h3 class="text-xs font-bold text-[#596273] dark:text-[#b8c1cf]">Cambios en máximos de la colección</h3>
            <ul class="flex flex-col gap-2">
                <li v-for="change in impact.stat_changes" :key="change.key" class="flex min-h-10 items-center justify-between gap-3 rounded-xl border border-line px-3 py-2 text-sm dark:border-white/10">
                    <span class="font-semibold">{{ change.label }}</span>
                    <span class="shrink-0 font-mono font-bold tabular-nums">
                        <span class="text-[#7b8392] dark:text-[#9aa5b5]">{{ change.before ?? '—' }}</span>
                        <span class="px-1.5" aria-hidden="true">→</span><span class="sr-only">pasa a</span>
                        {{ change.after ?? '—' }}
                    </span>
                </li>
            </ul>
        </div>

        <p v-if="impact.is_partial" class="rounded-xl bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900 dark:bg-amber-400/10 dark:text-amber-200" role="status">
            Vista previa parcial: algunas fichas no pudieron actualizarse.
        </p>
        <p v-if="!compact" class="text-xs leading-5 text-[#7b8392] dark:text-[#9aa5b5]">Estimación basada en los tipos y estadísticas base disponibles.</p>
    </section>
</template>
