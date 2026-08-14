<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import type { AssistantAction } from '@/types/assistant';
import { computed } from 'vue';

const props = defineProps<{
    action: AssistantAction;
    busy?: boolean;
}>();

defineEmits<{
    confirm: [action: AssistantAction];
    cancel: [action: AssistantAction];
}>();

const isAdd = computed(() => props.action.type === 'add_pokemon');
const isPending = computed(() => props.action.status === 'pending');
const title = computed(() => isAdd.value
    ? `Agregar a ${props.action.payload.display_name}`
    : `Eliminar a ${props.action.payload.display_name}`);
const description = computed(() => isAdd.value
    ? 'Se agregará a tu colección personal.'
    : 'También se perderán su apodo, notas y estado de favorito.');
</script>

<template>
    <article class="rounded-2xl border border-[#e1b8bd] bg-[#fff7f7] p-3.5 dark:border-[#8f3944]/60 dark:bg-[#341a22]">
        <div class="flex items-start gap-3">
            <img
                v-if="action.payload.image"
                :src="action.payload.image"
                :alt="`Ilustración de ${action.payload.display_name}`"
                class="size-14 shrink-0 rounded-xl bg-white object-contain p-1 outline outline-1 outline-black/10 dark:bg-white/90"
            />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-[#172033] dark:text-white">{{ title }}</p>
                <p class="mt-1 text-xs leading-5 text-[#697180] dark:text-[#bdc5d2]">{{ description }}</p>
            </div>
        </div>

        <div v-if="isPending" class="mt-3 grid grid-cols-2 gap-2">
            <button
                type="button"
                class="min-h-11 rounded-xl border border-line-strong bg-white px-3 text-sm font-bold text-[#505867] transition-colors hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] disabled:cursor-wait disabled:opacity-60 dark:border-white/15 dark:bg-white/5 dark:text-white dark:hover:bg-white/10"
                :disabled="busy"
                @click="$emit('cancel', action)"
            >
                Cancelar
            </button>
            <button
                type="button"
                class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[#c62f3d] px-3 text-sm font-bold text-white transition-[background-color,transform] hover:bg-[#aa2634] active:scale-[0.96] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-60 dark:focus-visible:ring-offset-[#341a22]"
                :disabled="busy"
                @click="$emit('confirm', action)"
            >
                <AppIcon v-if="!busy" name="check" class="size-4" />
                {{ busy ? 'Procesando…' : (isAdd ? 'Agregar Pokémon' : 'Eliminar Pokémon') }}
            </button>
        </div>
        <p v-else class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-[#626979] dark:text-[#bdc5d2]">
            <AppIcon name="check" class="size-4" />
            {{ action.status === 'executed' ? 'Acción completada' : action.status === 'cancelled' ? 'Acción cancelada' : 'Acción no disponible' }}
        </p>
    </article>
</template>
