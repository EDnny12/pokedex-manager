<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import PokemonImage from '@/Components/Pokemon/PokemonImage.vue';
import PokemonTypeBadge from '@/Components/Pokemon/PokemonTypeBadge.vue';
import type { CatalogPokemon, CollectionPokemon } from '@/types/pokemon';
import { formatPokemonId } from '@/utils/pokemon';
import { Link, router } from '@inertiajs/vue3';
import { computed, shallowRef } from 'vue';
import { route } from '../../../../vendor/tightenco/ziggy';

const props = defineProps<{
    pokemon: CollectionPokemon | CatalogPokemon;
    context: 'collection' | 'catalog';
}>();

const isAdding = shallowRef(false);
const collectionPokemon = computed(() => props.context === 'collection' ? props.pokemon as CollectionPokemon : null);
const catalogPokemon = computed(() => props.context === 'catalog' ? props.pokemon as CatalogPokemon : null);
const destination = computed(() => props.context === 'collection'
    ? route('collection.show', (props.pokemon as CollectionPokemon).collection_id)
    : route('pokedex.show', props.pokemon.id));

function addToCollection(): void {
    isAdding.value = true;
    router.post(route('collection.store'), { pokemon_id: props.pokemon.id }, {
        preserveScroll: true,
        invalidateCacheTags: ['collection'],
        onFinish: () => {
            isAdding.value = false;
        },
    });
}
</script>

<template>
    <article class="group relative flex min-w-0 flex-col overflow-hidden rounded-[1.35rem] border border-line bg-surface shadow-[0_10px_30px_rgba(23,32,51,0.05)] transition-[transform,box-shadow,border-color] hover:-translate-y-1 hover:border-[#bcb6aa] hover:shadow-[0_18px_42px_rgba(23,32,51,0.10)] focus-within:border-[#c62f3d] dark:border-white/10 dark:bg-[#161f2e] dark:hover:border-white/20">
        <div class="relative aspect-square overflow-hidden bg-surface-subtle p-4 dark:bg-[#111927] sm:p-5">
            <span class="pointer-events-none absolute -right-1 top-2 font-mono text-3xl font-bold tracking-[-0.08em] text-[#172033]/[0.07] dark:text-white/[0.06] sm:text-4xl">{{ formatPokemonId(pokemon.id) }}</span>
            <PokemonImage :src="pokemon.image" :alt="`Ilustración de ${pokemon.display_name}`" />
            <span v-if="collectionPokemon?.is_favorite" class="absolute left-3 top-3 grid size-9 place-items-center rounded-full border border-amber-300 bg-amber-50 text-lg text-amber-600 shadow-sm dark:border-amber-400/30 dark:bg-amber-400/15 dark:text-amber-300" aria-label="Favorito">★</span>
        </div>

        <div class="flex flex-1 flex-col gap-3 p-3.5 sm:p-4">
            <div class="min-w-0">
                <h2 class="truncate text-base font-bold tracking-[-0.02em] sm:text-lg">
                    <Link :href="destination" prefetch cache-tags="collection" class="rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d]">
                        <span class="absolute inset-0" aria-hidden="true" />
                        {{ collectionPokemon?.nickname || pokemon.display_name }}
                    </Link>
                </h2>
                <p class="mt-0.5 truncate font-mono text-[0.7rem] font-semibold uppercase tracking-[0.08em] text-[#747c8b] dark:text-[#96a1b2]">
                    <template v-if="collectionPokemon?.nickname">{{ pokemon.display_name }} · </template>{{ formatPokemonId(pokemon.id) }}
                </p>
            </div>

            <div class="flex min-h-7 flex-wrap gap-1.5">
                <PokemonTypeBadge v-for="type in pokemon.types" :key="type" :type="type" />
                <span v-if="pokemon.types.length === 0" class="text-xs text-[#777f8f]">Datos no disponibles</span>
            </div>
        </div>

        <div v-if="context === 'catalog'" class="relative z-10 border-t border-line p-3 dark:border-white/10">
            <Link v-if="catalogPokemon?.collection_id" :href="route('collection.show', catalogPokemon.collection_id)" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-200 dark:hover:bg-emerald-400/15">
                <AppIcon name="check" class="size-4" /> En tu colección
            </Link>
            <button v-else type="button" :disabled="isAdding" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#172033] px-3 py-2 text-xs font-bold text-white hover:bg-[#28344b] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] disabled:cursor-not-allowed disabled:opacity-60 dark:bg-surface-subtle dark:text-[#172033] dark:hover:bg-white" @click="addToCollection">
                <AppIcon name="plus" class="size-4" /> {{ isAdding ? 'Agregando…' : 'Agregar' }}
            </button>
        </div>
    </article>
</template>
