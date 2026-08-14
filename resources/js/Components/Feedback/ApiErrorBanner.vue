<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import { router } from '@inertiajs/vue3';

defineProps<{ message: string }>();
</script>

<template>
    <section role="alert" class="flex flex-col gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-amber-950 sm:flex-row sm:items-center dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
        <div class="flex flex-1 items-start gap-3">
            <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-full bg-amber-200/70 dark:bg-amber-300/15">
                <span class="font-mono font-bold" aria-hidden="true">!</span>
            </span>
            <p class="text-sm font-medium leading-6">{{ message }}</p>
        </div>
        <button type="button" class="flex min-h-11 items-center justify-center gap-2 rounded-xl border border-amber-400/60 px-4 py-2 text-sm font-bold hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600 dark:hover:bg-amber-300/10" @click="router.reload()">
            <AppIcon name="refresh" class="size-4" />
            Reintentar
        </button>
    </section>
</template>
