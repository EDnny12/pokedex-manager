<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import ApiErrorBanner from '@/Components/Feedback/ApiErrorBanner.vue';
import EmptyState from '@/Components/Feedback/EmptyState.vue';
import FlashMessage from '@/Components/Feedback/FlashMessage.vue';
import PaginationNav from '@/Components/Pokemon/PaginationNav.vue';
import PokemonGrid from '@/Components/Pokemon/PokemonGrid.vue';
import PokemonSkeletonGrid from '@/Components/Pokemon/PokemonSkeletonGrid.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CatalogPokemon, PaginatedPokemon } from '@/types/pokemon';
import { pokemonTypeLabel } from '@/utils/pokemon';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, reactive, shallowRef, useTemplateRef } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    catalog: PaginatedPokemon<CatalogPokemon>;
    filters: { q: string; type: string };
    types: string[];
    apiError: string | null;
    focusSearch: boolean;
}>();

const searchForm = reactive({
    q: props.filters.q,
    type: props.filters.type,
});

const searchInput = useTemplateRef<HTMLInputElement>('searchInput');
const loading = shallowRef(false);

onMounted(() => {
    if (props.focusSearch) {
        searchInput.value?.focus();
    }
});

function search(): void {
    loading.value = true;
    router.get(route('pokedex.index'), {
        q: searchForm.q.trim() || undefined,
        type: searchForm.type || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            loading.value = false;
        },
    });
}

function clearSearch(): void {
    searchForm.q = '';
    searchForm.type = '';
    search();
}
</script>

<template>
    <AppLayout>
        <Head title="Explorar Pokédex" />
        <FlashMessage />

        <div class="mx-auto flex w-full max-w-[96rem] flex-col gap-5 px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
            <header class="flex flex-col gap-2">
                <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.2em] text-[#9d3340] dark:text-[#f08f99]">Catálogo Pokémon</p>
                <h1 class="text-3xl font-bold tracking-[-0.045em] sm:text-4xl">Explorar Pokédex</h1>
                <p class="max-w-2xl text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">Busca por nombre, número o tipo y agrega nuevos Pokémon a tu colección.</p>
            </header>

            <ApiErrorBanner v-if="apiError" :message="apiError" />

            <form class="grid gap-3 rounded-[1.5rem] border border-line bg-surface p-3.5 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:p-4 dark:border-white/10 dark:bg-[#161f2e]" role="search" @submit.prevent="search">
                <label class="relative block">
                    <span class="sr-only">Buscar Pokémon por nombre o número</span>
                    <AppIcon name="search" class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-[#7b8392]" />
                    <input ref="searchInput" v-model="searchForm.q" type="search" autocomplete="off" class="min-h-12 w-full rounded-xl border-line-strong bg-white py-3 pl-12 pr-4 text-base placeholder:text-[#8a91a0] focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white dark:placeholder:text-[#7f8999]" placeholder="Nombre exacto, parcial o número…" />
                </label>
                <label>
                    <span class="sr-only">Filtrar por tipo</span>
                    <select v-model="searchForm.type" class="min-h-12 w-full rounded-xl border-line-strong bg-white py-3 pl-3 pr-9 text-sm font-semibold focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white">
                        <option value="">Todos los tipos</option>
                        <option v-for="type in types" :key="type" :value="type">{{ pokemonTypeLabel(type) }}</option>
                    </select>
                </label>
                <button type="submit" class="min-h-12 rounded-xl bg-[#172033] px-5 py-3 text-sm font-bold text-white hover:bg-[#28344b] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 dark:bg-surface-subtle dark:text-[#172033] dark:hover:bg-white dark:focus-visible:ring-offset-[#161f2e]">Buscar</button>
            </form>

            <div v-if="catalog.meta.total" class="flex items-center justify-between gap-3 px-1">
                <p class="text-xs font-medium text-[#697180] dark:text-[#aab4c4]">{{ catalog.meta.total }} {{ catalog.meta.total === 1 ? 'Pokémon encontrado' : 'Pokémon encontrados' }}</p>
                <button v-if="filters.q || filters.type" type="button" class="min-h-11 rounded-lg px-2 text-xs font-bold text-[#9d3340] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#f08f99]" @click="clearSearch">Limpiar búsqueda</button>
            </div>

            <PokemonSkeletonGrid v-if="loading" :count="12" />
            <PokemonGrid v-else-if="catalog.data.length" :pokemon="catalog.data" context="catalog" />

            <EmptyState v-else-if="!apiError && !loading" icon="search" title="No encontramos coincidencias" :description="filters.q ? `No hay resultados para “${filters.q}”. Revisa el nombre o prueba con su número.` : 'No hay Pokémon disponibles para este tipo.'" action-label="Ver toda la Pokédex" :action-href="route('pokedex.index')" />

            <PaginationNav v-if="catalog.data.length && !loading" :meta="catalog.meta" :base-url="route('pokedex.index')" :query="filters" />
        </div>
    </AppLayout>
</template>
