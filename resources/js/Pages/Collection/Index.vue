<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import CollectionFilters from '@/Components/Collection/CollectionFilters.vue';
import ApiErrorBanner from '@/Components/Feedback/ApiErrorBanner.vue';
import EmptyState from '@/Components/Feedback/EmptyState.vue';
import FlashMessage from '@/Components/Feedback/FlashMessage.vue';
import PokemonGrid from '@/Components/Pokemon/PokemonGrid.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CollectionPokemon } from '@/types/pokemon';
import { Head, Link } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    items: CollectionPokemon[];
    api_error: string | null;
}>();

const query = shallowRef('');
const scope = shallowRef<'all' | 'favorites'>('all');
const type = shallowRef('');
const sort = shallowRef<'recent' | 'number' | 'name-asc' | 'name-desc'>('recent');

const types = computed(() => Array.from(new Set(props.items.flatMap((pokemon) => pokemon.types))).sort());
const favoriteCount = computed(() => props.items.filter((pokemon) => pokemon.is_favorite).length);

const visiblePokemon = computed(() => {
    const normalizedQuery = query.value.trim().toLocaleLowerCase('es-MX');
    const filteredPokemon = props.items.filter((pokemon) => {
        const normalizedNumber = normalizedQuery.replace('#', '').replace(/^0+/, '') || '0';
        const matchesNumber = /^#?\d+$/.test(normalizedQuery)
            && pokemon.id === Number(normalizedNumber);
        const matchesScope = scope.value === 'all' || pokemon.is_favorite;
        const matchesType = type.value === '' || pokemon.types.includes(type.value);
        const matchesQuery = normalizedQuery === ''
            || pokemon.display_name.toLocaleLowerCase('es-MX').includes(normalizedQuery)
            || pokemon.name.toLocaleLowerCase('es-MX').includes(normalizedQuery)
            || (pokemon.nickname?.toLocaleLowerCase('es-MX').includes(normalizedQuery) ?? false)
            || matchesNumber;

        return matchesScope && matchesType && matchesQuery;
    });

    return [...filteredPokemon].sort((left, right) => {
        if (sort.value === 'number') {
            return left.id - right.id;
        }

        const leftName = left.nickname || left.display_name;
        const rightName = right.nickname || right.display_name;

        if (sort.value === 'name-asc') {
            return leftName.localeCompare(rightName, 'es');
        }

        if (sort.value === 'name-desc') {
            return rightName.localeCompare(leftName, 'es');
        }

        return new Date(right.added_at ?? 0).getTime() - new Date(left.added_at ?? 0).getTime();
    });
});

const hasActiveFilters = computed(() => query.value !== '' || scope.value !== 'all' || type.value !== '');

function clearFilters(): void {
    query.value = '';
    scope.value = 'all';
    type.value = '';
    sort.value = 'recent';
}
</script>

<template>
    <AppLayout>
        <Head title="Mi colección" />
        <FlashMessage />

        <div class="mx-auto flex w-full max-w-[96rem] flex-col gap-5 px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-col gap-2">
                    <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#9d3340] dark:text-[#f08f99]">Archivo personal</p>
                    <div>
                        <h1 class="text-3xl font-bold tracking-[-0.045em] sm:text-4xl">Mi colección</h1>
                        <p class="mt-1 text-sm text-[#697180] dark:text-[#aab4c4]">
                            {{ items.length }} Pokémon · {{ favoriteCount }} {{ favoriteCount === 1 ? 'favorito' : 'favoritos' }}
                        </p>
                    </div>
                </div>
                <Link :href="route('pokedex.index', { focus: 1 })" class="inline-flex min-h-12 items-center justify-center gap-2 self-stretch rounded-xl bg-[#c62f3d] px-5 py-3 text-sm font-bold text-white shadow-sm transition-[background-color,transform] hover:bg-[#aa2634] active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 sm:self-auto dark:focus-visible:ring-offset-[#0e1420]">
                    <AppIcon name="plus" class="size-5" />
                    Agregar Pokémon
                </Link>
            </header>

            <ApiErrorBanner v-if="api_error" :message="api_error" />

            <section v-if="items.length" aria-label="Resumen de la colección" class="grid grid-cols-3 divide-x divide-line overflow-hidden rounded-[1.35rem] border border-line bg-surface dark:divide-white/10 dark:border-white/10 dark:bg-[#161f2e]">
                <div class="flex flex-col gap-1 px-3 py-4 text-center sm:px-5 sm:py-5">
                    <span class="font-mono text-xl font-bold tabular-nums sm:text-2xl">{{ items.length }}</span>
                    <span class="text-[0.68rem] font-semibold text-[#707887] sm:text-xs dark:text-[#9ca7b7]">Pokémon</span>
                </div>
                <div class="flex flex-col gap-1 px-3 py-4 text-center sm:px-5 sm:py-5">
                    <span class="font-mono text-xl font-bold tabular-nums sm:text-2xl">{{ types.length }}</span>
                    <span class="text-[0.68rem] font-semibold text-[#707887] sm:text-xs dark:text-[#9ca7b7]">Tipos</span>
                </div>
                <div class="flex flex-col gap-1 px-3 py-4 text-center sm:px-5 sm:py-5">
                    <span class="font-mono text-xl font-bold tabular-nums sm:text-2xl">{{ favoriteCount }}</span>
                    <span class="text-[0.68rem] font-semibold text-[#707887] sm:text-xs dark:text-[#9ca7b7]">Favoritos</span>
                </div>
            </section>

            <template v-if="items.length">
                <CollectionFilters v-model:query="query" v-model:scope="scope" v-model:type="type" v-model:sort="sort" :types="types" :result-count="visiblePokemon.length" />

                <div class="flex items-center justify-between gap-3 px-1">
                    <p class="text-xs font-medium text-[#697180] dark:text-[#aab4c4]">{{ visiblePokemon.length }} {{ visiblePokemon.length === 1 ? 'resultado' : 'resultados' }}</p>
                    <button v-if="hasActiveFilters" type="button" class="min-h-11 rounded-lg px-2 text-xs font-bold text-[#9d3340] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#f08f99]" @click="clearFilters">Limpiar filtros</button>
                </div>

                <PokemonGrid v-if="visiblePokemon.length" :pokemon="visiblePokemon" context="collection" />
                <EmptyState v-else icon="search" title="No encontramos coincidencias" :description="query ? `No hay Pokémon en tu colección que coincidan con “${query}”.` : 'Prueba con otros filtros.'" action-label="Buscar en la Pokédex" :action-href="route('pokedex.index', { q: query })" />
            </template>

            <EmptyState v-else title="Tu colección está vacía" description="Explora la Pokédex y agrega tu primer Pokémon para comenzar." action-label="Explorar la Pokédex" :action-href="route('pokedex.index', { focus: 1 })" />
        </div>
    </AppLayout>
</template>
