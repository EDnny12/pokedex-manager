<script setup lang="ts">
import type { PokemonStatKey } from '@/types/pokemon';
import { statKeys, statLabels } from '@/utils/pokemon';

defineProps<{
    stats: Partial<Record<PokemonStatKey, number>>;
    comparisonStats?: Partial<Record<PokemonStatKey, number>> | null;
}>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <div v-for="stat in statKeys" :key="stat" class="grid grid-cols-[5.7rem_2rem_1fr] items-center gap-2 sm:grid-cols-[7rem_2.5rem_1fr] sm:gap-3">
            <span class="text-xs font-semibold text-[#697180] dark:text-[#aab4c4]">{{ statLabels[stat] }}</span>
            <span class="text-right font-mono text-xs font-bold tabular-nums">{{ stats[stat] ?? '—' }}</span>
            <div class="h-2 overflow-hidden rounded-full bg-skeleton dark:bg-white/10" :aria-label="`${statLabels[stat]}: ${stats[stat] ?? 0}`" role="meter" aria-valuemin="0" aria-valuemax="255" :aria-valuenow="stats[stat] ?? 0">
                <div class="h-full rounded-full bg-[#c62f3d]" :class="comparisonStats && (stats[stat] ?? 0) >= (comparisonStats[stat] ?? 0) ? 'bg-emerald-600 dark:bg-emerald-400' : ''" :style="{ width: `${Math.min(((stats[stat] ?? 0) / 255) * 100, 100)}%` }" />
            </div>
        </div>
    </div>
</template>
