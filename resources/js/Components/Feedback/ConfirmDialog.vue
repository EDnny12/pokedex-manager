<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import { nextTick, useId, useTemplateRef, watch } from 'vue';

const props = withDefaults(defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    processing?: boolean;
}>(), {
    confirmLabel: 'Eliminar',
    processing: false,
});

const emit = defineEmits<{
    close: [];
    confirm: [];
}>();

defineSlots<{
    details(): unknown;
}>();

const dialog = useTemplateRef<HTMLDialogElement>('dialog');
const cancelButton = useTemplateRef<HTMLButtonElement>('cancelButton');
const titleId = useId();
const descriptionId = useId();

watch(
    () => props.open,
    async (open) => {
        if (open && !dialog.value?.open) {
            dialog.value?.showModal();
            await nextTick();
            cancelButton.value?.focus();
        } else if (!open && dialog.value?.open) {
            dialog.value.close();
        }
    },
);

function handleClose(): void {
    if (!props.processing) {
        emit('close');
    }
}
</script>

<template>
    <Teleport to="body">
        <dialog ref="dialog" :aria-labelledby="titleId" :aria-describedby="descriptionId" class="m-auto w-[calc(100%-2rem)] max-w-md rounded-[1.5rem] bg-white p-0 text-[#172033] shadow-[0_28px_100px_rgba(9,14,24,0.35)] backdrop:bg-[#0e1420]/65 backdrop:backdrop-blur-sm dark:bg-[#192232] dark:text-white" @cancel.prevent="handleClose" @close="emit('close')">
            <div class="flex flex-col gap-5 p-6 sm:p-7">
                <span class="grid size-12 place-items-center rounded-2xl bg-red-50 text-[#b42534] dark:bg-[#c62f3d]/15 dark:text-[#f3a0a8]">
                    <AppIcon name="trash" class="size-6" />
                </span>
                <div class="flex flex-col gap-2">
                    <h2 :id="titleId" class="text-xl font-bold tracking-[-0.025em]">{{ title }}</h2>
                    <p :id="descriptionId" class="text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">{{ description }}</p>
                </div>
                <div v-if="$slots.details"><slot name="details" /></div>
                <div class="grid grid-cols-2 gap-3">
                    <button ref="cancelButton" type="button" :disabled="processing" class="min-h-12 rounded-xl border border-[#d5d1c8] px-4 py-3 text-sm font-bold hover:bg-canvas focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] disabled:opacity-50 dark:border-white/15 dark:hover:bg-white/5" @click="handleClose">Cancelar</button>
                    <button type="button" :disabled="processing" class="min-h-12 rounded-xl bg-[#b42534] px-4 py-3 text-sm font-bold text-white hover:bg-[#98202c] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:focus-visible:ring-offset-[#192232]" @click="emit('confirm')">{{ processing ? 'Eliminando…' : confirmLabel }}</button>
                </div>
            </div>
        </dialog>
    </Teleport>
</template>
