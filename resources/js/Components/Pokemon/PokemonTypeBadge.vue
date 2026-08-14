<script setup lang="ts">
import { computed } from 'vue';
import { pokemonTypeClasses, pokemonTypeLabel } from '@/utils/pokemon';

const props = defineProps<{ type: string }>();

const classes = computed(() => pokemonTypeClasses[props.type] ?? pokemonTypeClasses.normal);
const label = computed(() => pokemonTypeLabel(props.type));
</script>

<template>
    <span class="inline-flex min-h-7 items-center rounded-full border px-2.5 py-1 text-[0.68rem] font-bold uppercase tracking-[0.08em]" :class="classes">{{ label }}</span>
</template>
