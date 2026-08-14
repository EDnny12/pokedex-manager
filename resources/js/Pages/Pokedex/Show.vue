<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import CollectionImpactPreview from '@/Components/Collection/CollectionImpactPreview.vue';
import ApiErrorBanner from '@/Components/Feedback/ApiErrorBanner.vue';
import EmptyState from '@/Components/Feedback/EmptyState.vue';
import FlashMessage from '@/Components/Feedback/FlashMessage.vue';
import PokemonDetail from '@/Components/Pokemon/PokemonDetail.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CollectionImpact, Pokemon } from '@/types/pokemon';
import { Head, Link, router } from '@inertiajs/vue3';
import { shallowRef } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    pokemon: Pokemon | null;
    collectionId: number | null;
    additionImpact: CollectionImpact | null;
    apiError: string | null;
}>();

const adding = shallowRef(false);

function addToCollection(): void {
    if (!props.pokemon) {
        return;
    }

    adding.value = true;
    router.post(route('collection.store'), { pokemon_id: props.pokemon.id }, {
        preserveScroll: true,
        invalidateCacheTags: ['collection'],
        onFinish: () => {
            adding.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="pokemon?.display_name || 'Pokémon no disponible'" />
        <FlashMessage />

        <div class="mx-auto flex w-full max-w-[96rem] flex-col gap-5 px-4 py-6 sm:px-6 sm:py-8 xl:px-9">
            <Link :href="route('pokedex.index')" class="inline-flex min-h-11 items-center gap-2 self-start rounded-lg pr-3 text-sm font-bold text-[#697180] hover:text-[#172033] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#aab4c4] dark:hover:text-white">
                <AppIcon name="arrow-left" class="size-5" /> Volver a explorar
            </Link>

            <ApiErrorBanner v-if="apiError" :message="apiError" />

            <PokemonDetail v-if="pokemon" :pokemon="pokemon">
                <template #eyebrow>
                    <p class="font-mono text-[0.68rem] font-bold uppercase tracking-[0.18em] text-[#7b8392]">Ficha de la Pokédex</p>
                </template>
                <template #actions>
                    <Link v-if="collectionId" :href="route('collection.show', collectionId)" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#161f2e]">
                        <AppIcon name="check" class="size-5" /> Ver en mi colección
                    </Link>
                    <button v-else type="button" :disabled="adding" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-[#c62f3d] px-5 py-3 text-sm font-bold text-white hover:bg-[#aa2634] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-[#161f2e]" @click="addToCollection">
                        <AppIcon name="plus" class="size-5" /> {{ adding ? 'Agregando…' : 'Agregar a mi colección' }}
                    </button>
                    <Link :href="route('compare.index', { left: pokemon.name })" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-line-strong px-5 py-3 text-sm font-bold hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-white/15 dark:hover:bg-white/5">
                        <AppIcon name="compare" class="size-5" /> Comparar
                    </Link>
                </template>
                <template v-if="additionImpact && !collectionId" #aside>
                    <CollectionImpactPreview :impact="additionImpact" />
                </template>
            </PokemonDetail>

            <EmptyState v-else-if="!apiError" icon="search" title="Pokémon no encontrado" description="No encontramos la ficha que buscas en la Pokédex." action-label="Volver a la Pokédex" :action-href="route('pokedex.index')" />
        </div>
    </AppLayout>
</template>
