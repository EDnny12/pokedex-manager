<script setup lang="ts">
import PokemonCard from '@/Components/Pokemon/PokemonCard.vue';
import type { CatalogPokemon, CollectionPokemon } from '@/types/pokemon';

defineProps<{
    pokemon: Array<CollectionPokemon | CatalogPokemon>;
    context: 'collection' | 'catalog';
}>();
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4 2xl:grid-cols-5">
        <PokemonCard v-for="entry in pokemon" :key="`${context}-${entry.id}`" :pokemon="entry" :context="context" />
    </div>
</template>
