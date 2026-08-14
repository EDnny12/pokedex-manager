<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import AssistantPanel from '@/Components/Assistant/AssistantPanel.vue';
import { useAssistantChat } from '@/composables/useAssistantChat';
import { shallowRef } from 'vue';

const open = shallowRef(false);
const { hasPendingActions } = useAssistantChat();
</script>

<template>
    <button
        type="button"
        class="fixed bottom-[calc(5.5rem+env(safe-area-inset-bottom))] right-4 z-40 inline-flex min-h-12 items-center gap-2 rounded-2xl bg-[#172033] px-4 py-3 text-sm font-bold text-white shadow-[0_14px_35px_rgba(23,32,51,0.24)] transition-[background-color,transform] hover:bg-[#28344b] active:scale-[0.96] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 lg:bottom-7 lg:right-7 dark:bg-[#f7f4ed] dark:text-[#172033] dark:hover:bg-white dark:focus-visible:ring-offset-[#0e1420]"
        aria-haspopup="dialog"
        :aria-expanded="open"
        aria-label="Abrir chat con Lía"
        @click="open = true"
    >
        <span class="relative">
            <AppIcon name="chat" class="size-5" />
            <span v-if="hasPendingActions" class="absolute -right-1.5 -top-1.5 size-2.5 rounded-full bg-[#c62f3d] ring-2 ring-[#172033] dark:ring-[#f7f4ed]" aria-hidden="true" />
        </span>
        <span>Lía</span>
        <span v-if="hasPendingActions" class="sr-only">Hay una acción pendiente</span>
    </button>

    <AssistantPanel v-model="open" />
</template>
