<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import PokemonImage from '@/Components/Pokemon/PokemonImage.vue';
import PokemonStats from '@/Components/Pokemon/PokemonStats.vue';
import PokemonTypeBadge from '@/Components/Pokemon/PokemonTypeBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Pokemon, PokemonStatKey } from '@/types/pokemon';
import { formatPokemonId, statKeys, statLabels } from '@/utils/pokemon';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    filters: { left: string; right: string };
    leftPokemon: Pokemon | null;
    rightPokemon: Pokemon | null;
    comparisonErrors: Partial<Record<'left' | 'right', string>>;
}>();

const compareForm = reactive({ ...props.filters });

const winnerByStat = computed(() => {
    if (!props.leftPokemon || !props.rightPokemon) {
        return {} as Partial<Record<PokemonStatKey, 'left' | 'right' | 'tie'>>;
    }

    return Object.fromEntries(statKeys.map((stat) => {
        const left = props.leftPokemon?.stats[stat] ?? 0;
        const right = props.rightPokemon?.stats[stat] ?? 0;
        const winner = left === right ? 'tie' : left > right ? 'left' : 'right';

        return [stat, winner];
    }));
});

function compare(): void {
    router.get(route('compare.index'), {
        left: compareForm.left.trim() || undefined,
        right: compareForm.right.trim() || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
}

function swap(): void {
    const left = compareForm.left;
    compareForm.left = compareForm.right;
    compareForm.right = left;
    compare();
}
</script>

<template>
    <AppLayout>
        <Head title="Comparador" />

        <div class="mx-auto flex w-full max-w-[96rem] flex-col gap-5 px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
            <header class="flex flex-col gap-2">
                <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#9d3340] dark:text-[#f08f99]">Comparación</p>
                <h1 class="text-3xl font-bold tracking-[-0.045em] sm:text-4xl">Comparar Pokémon</h1>
                <p class="max-w-2xl text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">Compara las estadísticas base de dos Pokémon por nombre o número de la Pokédex.</p>
            </header>

            <form class="grid gap-3 rounded-[1.5rem] border border-line bg-surface p-4 sm:grid-cols-[1fr_auto_1fr_auto] sm:items-end dark:border-white/10 dark:bg-[#161f2e]" @submit.prevent="compare">
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-bold">Primer Pokémon</span>
                    <input v-model="compareForm.left" type="text" autocomplete="off" class="min-h-12 rounded-xl border-line-strong bg-white text-base focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white" placeholder="Pikachu o 25" :aria-invalid="Boolean(comparisonErrors.left)" :aria-describedby="comparisonErrors.left ? 'left-error' : undefined" />
                    <span v-if="comparisonErrors.left" id="left-error" class="text-sm font-medium text-[#b42534] dark:text-[#f3a0a8]">{{ comparisonErrors.left }}</span>
                </label>
                <button type="button" class="grid min-h-12 min-w-12 place-items-center rounded-xl border border-line-strong hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-white/15 dark:hover:bg-white/5" aria-label="Intercambiar Pokémon" @click="swap">
                    <AppIcon name="compare" class="size-5 sm:rotate-0" />
                </button>
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-bold">Segundo Pokémon</span>
                    <input v-model="compareForm.right" type="text" autocomplete="off" class="min-h-12 rounded-xl border-line-strong bg-white text-base focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white" placeholder="Jolteon o 135" :aria-invalid="Boolean(comparisonErrors.right)" :aria-describedby="comparisonErrors.right ? 'right-error' : undefined" />
                    <span v-if="comparisonErrors.right" id="right-error" class="text-sm font-medium text-[#b42534] dark:text-[#f3a0a8]">{{ comparisonErrors.right }}</span>
                </label>
                <button type="submit" class="min-h-12 rounded-xl bg-[#172033] px-5 py-3 text-sm font-bold text-white hover:bg-[#28344b] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 dark:bg-surface-subtle dark:text-[#172033] dark:hover:bg-white dark:focus-visible:ring-offset-[#161f2e]">Comparar</button>
            </form>

            <section v-if="leftPokemon && rightPokemon" class="overflow-hidden rounded-[1.75rem] border border-line bg-surface dark:border-white/10 dark:bg-[#161f2e]">
                <div class="grid grid-cols-2 divide-x divide-line dark:divide-white/10">
                    <article v-for="(pokemon, side) in { left: leftPokemon, right: rightPokemon }" :key="side" class="flex min-w-0 flex-col items-center gap-3 p-4 text-center sm:p-7">
                        <div class="aspect-square w-full max-w-56 rounded-[1.5rem] bg-surface-subtle p-3 dark:bg-[#111927] sm:p-5">
                            <PokemonImage :src="pokemon.image" :alt="`Ilustración de ${pokemon.display_name}`" eager />
                        </div>
                        <div class="min-w-0">
                            <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.12em] text-[#7a8291]">{{ formatPokemonId(pokemon.id) }}</p>
                            <h2 class="truncate text-lg font-bold tracking-[-0.025em] sm:text-2xl">{{ pokemon.display_name }}</h2>
                        </div>
                        <div class="flex flex-wrap justify-center gap-1.5">
                            <PokemonTypeBadge v-for="type in pokemon.types" :key="type" :type="type" />
                        </div>
                    </article>
                </div>

                <div class="border-t border-line p-4 sm:p-7 dark:border-white/10">
                    <h2 class="mb-5 text-xl font-bold tracking-[-0.025em]">Resultado por estadística</h2>
                    <div class="flex flex-col divide-y divide-[#e2ded5] dark:divide-white/10">
                        <div v-for="stat in statKeys" :key="stat" class="grid grid-cols-[1fr_6rem_1fr] items-center gap-2 py-3 sm:grid-cols-[1fr_10rem_1fr]">
                            <span class="justify-self-end rounded-lg px-2.5 py-1.5 font-mono text-base font-bold tabular-nums" :class="winnerByStat[stat] === 'left' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200' : ''">{{ leftPokemon.stats[stat] ?? '—' }}</span>
                            <span class="text-center text-[0.68rem] font-bold text-[#697180] sm:text-xs dark:text-[#aab4c4]">{{ statLabels[stat] }}</span>
                            <span class="justify-self-start rounded-lg px-2.5 py-1.5 font-mono text-base font-bold tabular-nums" :class="winnerByStat[stat] === 'right' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200' : ''">{{ rightPokemon.stats[stat] ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="grid gap-4 md:grid-cols-2">
                <div v-for="(pokemon, side) in { left: leftPokemon, right: rightPokemon }" :key="side" class="flex min-h-64 flex-col items-center justify-center gap-4 rounded-[1.5rem] border border-line bg-white p-6 text-center dark:border-white/15 dark:bg-[#161f2e]">
                    <template v-if="pokemon">
                        <div class="size-32"><PokemonImage :src="pokemon.image" :alt="`Ilustración de ${pokemon.display_name}`" eager /></div>
                        <div><p class="font-mono text-xs font-bold text-[#7a8291]">{{ formatPokemonId(pokemon.id) }}</p><p class="text-xl font-bold">{{ pokemon.display_name }}</p></div>
                    </template>
                    <template v-else>
                        <span class="grid size-14 place-items-center rounded-2xl bg-surface-subtle text-[#9b3440] dark:bg-white/5 dark:text-[#f08f99]"><AppIcon name="search" class="size-7" /></span>
                        <p class="max-w-xs text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">Escribe {{ side === 'left' ? 'el primer' : 'el segundo' }} Pokémon para completar la comparación.</p>
                    </template>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
