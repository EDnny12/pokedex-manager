<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import ApiErrorBanner from '@/Components/Feedback/ApiErrorBanner.vue';
import EmptyState from '@/Components/Feedback/EmptyState.vue';
import PokemonTypeBadge from '@/Components/Pokemon/PokemonTypeBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CollectionInsights } from '@/types/pokemon';
import { pokemonTypeLabel } from '@/utils/pokemon';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    insights: CollectionInsights;
    apiError: string | null;
}>();

const maxTypeCount = computed(() => Math.max(...props.insights.type_distribution.map((type) => type.count), 1));
</script>

<template>
    <AppLayout>
        <Head title="Análisis" />

        <div class="mx-auto flex w-full max-w-[96rem] flex-col gap-5 px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
            <header class="flex flex-col gap-2">
                <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#9d3340] dark:text-[#f08f99]">Lectura de colección</p>
                <h1 class="text-3xl font-bold tracking-[-0.045em] sm:text-4xl">Análisis</h1>
                <p class="max-w-2xl text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">Descubre la variedad, la cobertura de tipos y las fortalezas de tu colección.</p>
            </header>

            <ApiErrorBanner v-if="apiError" :message="apiError" />

            <template v-if="insights.total">
                <section aria-label="Resumen" class="grid gap-3 sm:grid-cols-3">
                    <article class="rounded-[1.4rem] border border-line bg-surface p-5 dark:border-white/10 dark:bg-[#161f2e]">
                        <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.15em] text-[#7a8291] dark:text-[#96a1b2]">Tu colección</p>
                        <p class="mt-3 font-mono text-4xl font-bold tracking-[-0.06em] tabular-nums">{{ insights.total }}</p>
                        <p class="mt-1 text-sm text-[#697180] dark:text-[#aab4c4]">En tu colección</p>
                    </article>
                    <article class="rounded-[1.4rem] border border-line bg-surface p-5 dark:border-white/10 dark:bg-[#161f2e]">
                        <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.15em] text-[#7a8291] dark:text-[#96a1b2]">Tipos representados</p>
                        <p class="mt-3 font-mono text-4xl font-bold tracking-[-0.06em] tabular-nums">{{ insights.represented_types }}<span class="text-lg text-[#8a91a0]"> / {{ insights.total_types }}</span></p>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-skeleton dark:bg-white/10" role="meter" aria-label="Cobertura de tipos" aria-valuemin="0" :aria-valuemax="insights.total_types" :aria-valuenow="insights.represented_types">
                            <div class="h-full rounded-full bg-[#c62f3d]" :style="{ width: `${(insights.represented_types / insights.total_types) * 100}%` }" />
                        </div>
                    </article>
                    <article class="rounded-[1.4rem] border border-line bg-surface p-5 dark:border-white/10 dark:bg-[#161f2e]">
                        <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.15em] text-[#7a8291] dark:text-[#96a1b2]">Favoritos</p>
                        <p class="mt-3 font-mono text-4xl font-bold tracking-[-0.06em] tabular-nums">{{ insights.favorites }}</p>
                        <p class="mt-1 text-sm text-[#697180] dark:text-[#aab4c4]">Marcados con ★</p>
                    </article>
                </section>

                <div class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(20rem,0.8fr)]">
                    <section class="rounded-[1.6rem] border border-line bg-surface p-5 sm:p-6 dark:border-white/10 dark:bg-[#161f2e]">
                        <div class="flex items-end justify-between gap-4">
                            <div>
                                <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.15em] text-[#7a8291] dark:text-[#96a1b2]">Composición</p>
                                <h2 class="mt-1 text-xl font-bold tracking-[-0.025em]">Distribución por tipos</h2>
                            </div>
                            <span class="text-xs text-[#7a8291]">Incluye tipos secundarios</span>
                        </div>
                        <ol class="mt-6 flex flex-col gap-4">
                            <li v-for="type in insights.type_distribution" :key="type.name" class="grid grid-cols-[5.8rem_1fr_2rem] items-center gap-3 sm:grid-cols-[7rem_1fr_2.5rem]">
                                <span class="text-sm font-bold">{{ pokemonTypeLabel(type.name) }}</span>
                                <div class="h-2.5 overflow-hidden rounded-full bg-skeleton dark:bg-white/10">
                                    <div class="h-full rounded-full bg-[#c62f3d]" :style="{ width: `${(type.count / maxTypeCount) * 100}%` }" />
                                </div>
                                <span class="text-right font-mono text-sm font-bold tabular-nums">{{ type.count }}</span>
                            </li>
                        </ol>
                    </section>

                    <div class="flex flex-col gap-5">
                        <section v-if="insights.dominant_type" class="rounded-[1.6rem] border border-line bg-[#172033] p-5 text-white sm:p-6 dark:border-white/10 dark:bg-[#202b3e]">
                            <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.15em] text-white/60">Tipo dominante</p>
                            <div class="mt-5 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-2xl font-bold tracking-[-0.035em]">{{ pokemonTypeLabel(insights.dominant_type.name) }}</p>
                                    <p class="mt-1 text-sm text-white/65">{{ insights.dominant_type.count }} de tus Pokémon tienen este tipo.</p>
                                </div>
                                <PokemonTypeBadge :type="insights.dominant_type.name" />
                            </div>
                        </section>

                        <section class="rounded-[1.6rem] border border-line bg-surface p-5 sm:p-6 dark:border-white/10 dark:bg-[#161f2e]">
                            <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.15em] text-[#7a8291] dark:text-[#96a1b2]">Por descubrir</p>
                            <h2 class="mt-1 text-xl font-bold tracking-[-0.025em]">Tipos que te faltan</h2>
                            <div v-if="insights.missing_types.length" class="mt-4 flex flex-wrap gap-2">
                                <PokemonTypeBadge v-for="type in insights.missing_types" :key="type" :type="type" />
                            </div>
                            <p v-else class="mt-4 text-sm leading-6 text-emerald-700 dark:text-emerald-300">¡Ya tienes representados los 18 tipos!</p>
                        </section>
                    </div>
                </div>

                <section class="rounded-[1.6rem] border border-line bg-surface p-5 sm:p-6 dark:border-white/10 dark:bg-[#161f2e]">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="font-mono text-[0.65rem] font-bold uppercase tracking-[0.15em] text-[#7a8291] dark:text-[#96a1b2]">Fortalezas</p>
                            <h2 class="mt-1 text-xl font-bold tracking-[-0.025em]">Mejores estadísticas</h2>
                        </div>
                        <Link :href="route('compare.index')" class="inline-flex min-h-11 items-center gap-2 self-start rounded-lg text-sm font-bold text-[#9d3340] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#f08f99]">
                            <AppIcon name="compare" class="size-4" /> Abrir comparador
                        </Link>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <Link v-for="stat in insights.top_stats" :key="stat.key" :href="route('collection.show', stat.collection_id)" class="group flex min-h-24 items-center justify-between gap-4 rounded-2xl border border-line bg-white p-4 hover:border-[#c3bdb1] hover:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-white/10 dark:bg-white/[0.03] dark:hover:border-white/20">
                            <span class="min-w-0">
                                <span class="block font-mono text-[0.62rem] font-bold uppercase tracking-[0.12em] text-[#7a8291] dark:text-[#96a1b2]">{{ stat.label }}</span>
                                <span class="mt-1 block truncate text-sm font-bold group-hover:text-[#9d3340] dark:group-hover:text-[#f08f99]">{{ stat.pokemon_name }}</span>
                            </span>
                            <span class="font-mono text-2xl font-bold tabular-nums">{{ stat.value }}</span>
                        </Link>
                    </div>
                </section>
            </template>

            <EmptyState v-else icon="insights" title="Aún no hay datos que analizar" description="Agrega Pokémon a tu colección para descubrir tus tipos dominantes, cobertura y mejores estadísticas." action-label="Explorar Pokémon" :action-href="route('pokedex.index')" />
        </div>
    </AppLayout>
</template>
