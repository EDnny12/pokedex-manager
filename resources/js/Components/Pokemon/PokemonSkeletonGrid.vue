<script setup lang="ts">
withDefaults(defineProps<{ count?: number }>(), {
    count: 10,
});
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 xl:grid-cols-4 2xl:grid-cols-5" role="status" aria-label="Cargando Pokémon">
        <article v-for="index in count" :key="index" class="overflow-hidden rounded-[1.35rem] border border-line bg-surface dark:border-white/10 dark:bg-[#161f2e]">
            <div class="aspect-square bg-skeleton motion-safe:animate-pulse dark:bg-white/[0.05]" />
            <div class="flex flex-col gap-3 p-4">
                <div class="h-5 w-2/3 rounded bg-skeleton motion-safe:animate-pulse dark:bg-white/[0.06]" />
                <div class="h-3 w-1/3 rounded bg-skeleton motion-safe:animate-pulse dark:bg-white/[0.06]" />
                <div class="h-7 w-20 rounded-full bg-skeleton motion-safe:animate-pulse dark:bg-white/[0.06]" />
            </div>
        </article>
        <span class="sr-only">Cargando Pokémon…</span>
    </div>
</template>
