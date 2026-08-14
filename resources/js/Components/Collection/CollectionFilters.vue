<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import { pokemonTypeLabel } from '@/utils/pokemon';

defineProps<{
    types: string[];
    resultCount: number;
}>();

const query = defineModel<string>('query', { required: true });
const scope = defineModel<'all' | 'favorites'>('scope', { required: true });
const type = defineModel<string>('type', { required: true });
const sort = defineModel<'recent' | 'number' | 'name-asc' | 'name-desc'>('sort', { required: true });
</script>

<template>
    <section aria-label="Buscar y filtrar colección" class="flex flex-col gap-3 rounded-[1.5rem] border border-line bg-surface p-3.5 sm:p-4 dark:border-white/10 dark:bg-[#161f2e]">
        <label class="relative block">
            <span class="sr-only">Buscar en mi colección</span>
            <AppIcon name="search" class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-[#7b8392]" />
            <input v-model="query" type="search" autocomplete="off" class="min-h-12 w-full rounded-xl border-line-strong bg-white py-3 pl-12 pr-4 text-base placeholder:text-[#8a91a0] focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white dark:placeholder:text-[#7f8999]" placeholder="Buscar por nombre, apodo o número…" />
        </label>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex gap-1 rounded-xl bg-surface-subtle p-1 dark:bg-[#111927]" aria-label="Mostrar" role="group">
                <button type="button" class="min-h-10 flex-1 rounded-lg px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] lg:flex-none" :class="scope === 'all' ? 'bg-white text-[#172033] shadow-sm dark:bg-[#283346] dark:text-white' : 'text-[#697180] dark:text-[#9fa9b9]'" :aria-pressed="scope === 'all'" @click="scope = 'all'">Todos</button>
                <button type="button" class="min-h-10 flex-1 rounded-lg px-4 py-2 text-sm font-bold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] lg:flex-none" :class="scope === 'favorites' ? 'bg-white text-[#172033] shadow-sm dark:bg-[#283346] dark:text-white' : 'text-[#697180] dark:text-[#9fa9b9]'" :aria-pressed="scope === 'favorites'" @click="scope = 'favorites'">Favoritos</button>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center">
                <label>
                    <span class="sr-only">Filtrar por tipo</span>
                    <select v-model="type" class="min-h-11 w-full rounded-xl border-line-strong bg-white py-2 pl-3 pr-9 text-sm font-semibold focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white">
                        <option value="">Todos los tipos</option>
                        <option v-for="pokemonType in types" :key="pokemonType" :value="pokemonType">{{ pokemonTypeLabel(pokemonType) }}</option>
                    </select>
                </label>
                <label>
                    <span class="sr-only">Ordenar colección</span>
                    <select v-model="sort" class="min-h-11 w-full rounded-xl border-line-strong bg-white py-2 pl-3 pr-9 text-sm font-semibold focus:border-[#c62f3d] focus:ring-[#c62f3d] dark:border-white/10 dark:bg-[#111927] dark:text-white">
                        <option value="recent">Agregados recientes</option>
                        <option value="number">Número Pokédex</option>
                        <option value="name-asc">Nombre A–Z</option>
                        <option value="name-desc">Nombre Z–A</option>
                    </select>
                </label>
            </div>
        </div>

        <p class="sr-only" aria-live="polite">{{ resultCount }} resultados</p>
    </section>
</template>
