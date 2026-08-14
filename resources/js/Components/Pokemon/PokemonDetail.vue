<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import PokemonImage from '@/Components/Pokemon/PokemonImage.vue';
import PokemonStats from '@/Components/Pokemon/PokemonStats.vue';
import PokemonTypeBadge from '@/Components/Pokemon/PokemonTypeBadge.vue';
import type { Pokemon } from '@/types/pokemon';
import { formatPokemonId } from '@/utils/pokemon';

defineProps<{ pokemon: Pokemon }>();

defineSlots<{
    eyebrow(): unknown;
    actions(): unknown;
    aside(): unknown;
}>();
</script>

<template>
    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(22rem,0.85fr)]">
        <section class="overflow-hidden rounded-[1.75rem] border border-line bg-surface dark:border-white/10 dark:bg-[#161f2e]">
            <div class="grid gap-6 p-5 sm:p-7 lg:grid-cols-[minmax(16rem,0.8fr)_minmax(18rem,1.2fr)] lg:items-center">
                <div class="relative aspect-square overflow-hidden rounded-[1.5rem] bg-surface-subtle p-5 dark:bg-[#111927]">
                    <span class="absolute right-4 top-3 font-mono text-4xl font-bold tracking-[-0.08em] text-[#172033]/[0.07] dark:text-white/[0.06]">{{ formatPokemonId(pokemon.id) }}</span>
                    <PokemonImage :src="pokemon.image" :alt="`Ilustración de ${pokemon.display_name}`" eager />
                </div>

                <div class="flex min-w-0 flex-col gap-6">
                    <div class="flex flex-col gap-3">
                        <div v-if="$slots.eyebrow"><slot name="eyebrow" /></div>
                        <div>
                            <p class="font-mono text-xs font-bold uppercase tracking-[0.16em] text-[#9d3340] dark:text-[#f08f99]">{{ formatPokemonId(pokemon.id) }}</p>
                            <h1 class="mt-1 text-3xl font-bold tracking-[-0.04em] sm:text-4xl">{{ pokemon.display_name }}</h1>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <PokemonTypeBadge v-for="type in pokemon.types" :key="type" :type="type" />
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-line bg-white p-4 dark:border-white/10 dark:bg-white/[0.03]">
                            <dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-[#7a8291] dark:text-[#96a1b2]">Altura</dt>
                            <dd class="mt-1 text-xl font-bold">{{ pokemon.height_m !== null ? `${pokemon.height_m} m` : '—' }}</dd>
                        </div>
                        <div class="rounded-2xl border border-line bg-white p-4 dark:border-white/10 dark:bg-white/[0.03]">
                            <dt class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-[#7a8291] dark:text-[#96a1b2]">Peso</dt>
                            <dd class="mt-1 text-xl font-bold">{{ pokemon.weight_kg !== null ? `${pokemon.weight_kg} kg` : '—' }}</dd>
                        </div>
                    </dl>

                    <div v-if="pokemon.abilities.length" class="flex flex-col gap-2">
                        <h2 class="text-sm font-bold">Habilidades</h2>
                        <ul class="flex flex-wrap gap-2">
                            <li v-for="ability in pokemon.abilities" :key="ability.name" class="rounded-full border border-line px-3 py-1.5 text-xs font-semibold dark:border-white/15">
                                {{ ability.name }}<span v-if="ability.is_hidden" class="text-[#7a8291] dark:text-[#96a1b2]"> · oculta</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="$slots.actions" class="flex flex-wrap gap-3"><slot name="actions" /></div>
                </div>
            </div>

            <div v-if="Object.keys(pokemon.stats).length" class="border-t border-line p-5 sm:p-7 dark:border-white/10">
                <h2 class="mb-5 text-lg font-bold tracking-[-0.02em]">Estadísticas base</h2>
                <PokemonStats :stats="pokemon.stats" />
            </div>
        </section>

        <aside v-if="$slots.aside" class="min-w-0"><slot name="aside" /></aside>
    </div>
</template>
