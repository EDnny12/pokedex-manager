<script setup lang="ts">
import { getOptimizedPokemonImageUrl } from '@/utils/pokemon';
import { computed, shallowRef, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        src: string | null;
        alt: string;
        eager?: boolean;
        width?: number;
    }>(),
    {
        eager: false,
        width: 360,
    },
);

const optimizedSrc = computed(() => getOptimizedPokemonImageUrl(props.src, props.width));
const currentSrc = shallowRef<string | null>(optimizedSrc.value);
const hasError = shallowRef(false);

watch(
    [() => props.src, () => props.width],
    () => {
        hasError.value = false;
        currentSrc.value = optimizedSrc.value;
    },
);

function handleError(): void {
    // If the optimized WebP fails, fallback to the raw original PNG
    if (currentSrc.value && currentSrc.value !== props.src && props.src) {
        currentSrc.value = props.src;
        return;
    }

    // If the raw image also fails or src is null, render the vector Pokéball placeholder
    hasError.value = true;
}
</script>

<template>
    <img
        v-if="currentSrc && !hasError"
        :src="currentSrc"
        :alt="alt"
        :loading="eager ? 'eager' : 'lazy'"
        decoding="async"
        class="size-full object-contain"
        @error="handleError"
    />
    <div v-else class="grid size-full place-items-center" role="img" :aria-label="alt">
        <span class="relative block size-16 overflow-hidden rounded-full border-[5px] border-[#aab0bb] opacity-70 dark:border-[#778194]" aria-hidden="true">
            <span class="absolute inset-x-0 top-1/2 h-2 -translate-y-1/2 bg-[#aab0bb] dark:bg-[#778194]" />
            <span class="absolute left-1/2 top-1/2 size-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[5px] border-[#aab0bb] bg-canvas dark:border-[#778194] dark:bg-[#192232]" />
        </span>
    </div>
</template>

