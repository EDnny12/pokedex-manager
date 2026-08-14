<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import { Link } from '@inertiajs/vue3';

withDefaults(defineProps<{
    title: string;
    description: string;
    actionLabel?: string;
    actionHref?: string;
    icon?: 'collection' | 'search' | 'insights';
}>(), {
    actionLabel: undefined,
    actionHref: undefined,
    icon: 'collection',
});
</script>

<template>
    <section class="flex min-h-64 flex-col items-center justify-center gap-5 rounded-[1.75rem] border border-line bg-surface px-6 py-10 text-center shadow-[0_8px_28px_rgba(23,32,51,0.04)] dark:border-white/15 dark:bg-[#151d2b] sm:min-h-72 sm:py-12">
        <span class="grid size-14 place-items-center rounded-2xl border border-line bg-surface-subtle text-[#9b3440] dark:border-white/10 dark:bg-white/5 dark:text-[#f08f99]">
            <AppIcon :name="icon" class="size-7" />
        </span>
        <div class="flex max-w-md flex-col gap-2">
            <h2 class="text-xl font-bold tracking-[-0.025em]">{{ title }}</h2>
            <p class="text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">{{ description }}</p>
        </div>
        <Link v-if="actionLabel && actionHref" :href="actionHref" class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#c62f3d] px-5 py-3 text-sm font-bold text-white shadow-sm transition-[background-color,transform] hover:bg-[#aa2634] active:scale-[0.98] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-[#151d2b]">
            {{ actionLabel }}
        </Link>
    </section>
</template>
