<script setup lang="ts">
import { shallowRef } from 'vue';

defineProps<{
    src: string | null;
    alt: string;
    eager?: boolean;
}>();

const hasError = shallowRef(false);
</script>

<template>
    <img v-if="src && !hasError" :src="src" :alt="alt" :loading="eager ? 'eager' : 'lazy'" decoding="async" class="size-full object-contain" @error="hasError = true" />
    <div v-else class="grid size-full place-items-center" role="img" :aria-label="alt">
        <span class="relative block size-16 overflow-hidden rounded-full border-[5px] border-[#aab0bb] opacity-70 dark:border-[#778194]" aria-hidden="true">
            <span class="absolute inset-x-0 top-1/2 h-2 -translate-y-1/2 bg-[#aab0bb] dark:bg-[#778194]" />
            <span class="absolute left-1/2 top-1/2 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[5px] border-[#aab0bb] bg-canvas dark:border-[#778194] dark:bg-[#192232]" />
        </span>
    </div>
</template>
