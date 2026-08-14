<script setup lang="ts">
import type { PaginationMeta } from '@/types/pokemon';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    meta: PaginationMeta;
    baseUrl: string;
    query: Record<string, string>;
}>();

const pages = computed(() => {
    const start = Math.max(1, props.meta.current_page - 2);
    const end = Math.min(props.meta.last_page, start + 4);
    const adjustedStart = Math.max(1, end - 4);

    return Array.from({ length: end - adjustedStart + 1 }, (_, index) => adjustedStart + index);
});

function pageUrl(page: number): string {
    const parameters = new URLSearchParams();

    for (const [key, value] of Object.entries(props.query)) {
        if (value) {
            parameters.set(key, value);
        }
    }

    parameters.set('page', String(page));

    return `${props.baseUrl}?${parameters.toString()}`;
}
</script>

<template>
    <nav v-if="meta.last_page > 1" class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-line bg-surface p-3 sm:flex-row dark:border-white/10 dark:bg-[#161f2e]" aria-label="Paginación de Pokédex">
        <p class="text-xs font-medium text-[#697180] dark:text-[#aab4c4]">Mostrando {{ meta.from }}–{{ meta.to }} de {{ meta.total }}</p>
        <div class="flex items-center gap-1">
            <Link v-if="meta.current_page > 1" :href="pageUrl(meta.current_page - 1)" preserve-scroll class="grid min-h-11 min-w-11 place-items-center rounded-xl px-3 text-sm font-bold hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:hover:bg-white/5" aria-label="Página anterior">‹</Link>
            <span v-else class="grid min-h-11 min-w-11 place-items-center px-3 text-[#b0b4bd]" aria-hidden="true">‹</span>

            <Link v-for="page in pages" :key="page" :href="pageUrl(page)" preserve-scroll class="grid min-h-11 min-w-11 place-items-center rounded-xl px-3 text-sm font-bold focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d]" :class="page === meta.current_page ? 'bg-[#172033] text-white dark:bg-[#f7f4ed] dark:text-[#172033]' : 'hover:bg-surface-subtle dark:hover:bg-white/5'" :aria-current="page === meta.current_page ? 'page' : undefined">{{ page }}</Link>

            <Link v-if="meta.current_page < meta.last_page" :href="pageUrl(meta.current_page + 1)" preserve-scroll class="grid min-h-11 min-w-11 place-items-center rounded-xl px-3 text-sm font-bold hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:hover:bg-white/5" aria-label="Página siguiente">›</Link>
            <span v-else class="grid min-h-11 min-w-11 place-items-center px-3 text-[#b0b4bd]" aria-hidden="true">›</span>
        </div>
    </nav>
</template>
