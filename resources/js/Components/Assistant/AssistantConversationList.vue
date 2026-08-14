<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import type { AssistantConversation } from '@/types/assistant';
import { shallowRef } from 'vue';

defineProps<{
    conversations: readonly AssistantConversation[];
    activeId?: string;
}>();

const emit = defineEmits<{
    select: [conversation: AssistantConversation];
    delete: [conversation: AssistantConversation];
}>();

const confirmingId = shallowRef<string | null>(null);

function requestDelete(conversation: AssistantConversation): void {
    if (confirmingId.value === conversation.id) {
        emit('delete', conversation);
        confirmingId.value = null;
        return;
    }

    confirmingId.value = conversation.id;
}
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <p class="px-3 pb-2 font-mono text-[0.66rem] font-bold uppercase tracking-[0.18em] text-[#8b91a0] dark:text-[#9aa5b5]">Conversaciones</p>
        <div class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-1">
            <p v-if="conversations.length === 0" class="px-3 py-6 text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">Aún no tienes conversaciones.</p>
            <div
                v-for="conversation in conversations"
                :key="conversation.id"
                class="group rounded-xl p-1"
                :class="conversation.id === activeId ? 'bg-surface-subtle dark:bg-white/10' : ''"
            >
                <button
                    type="button"
                    class="w-full rounded-lg px-2 py-2 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d]"
                    @click="$emit('select', conversation)"
                >
                    <span class="block truncate text-sm font-semibold text-[#303849] dark:text-[#e8ebf0]">{{ conversation.title }}</span>
                    <span v-if="conversation.preview" class="mt-0.5 block truncate text-xs text-[#777f8f] dark:text-[#9aa5b5]">{{ conversation.preview }}</span>
                </button>
                <div class="flex items-center justify-end gap-1 px-1 pb-1">
                    <button
                        v-if="confirmingId === conversation.id"
                        type="button"
                        class="min-h-9 rounded-lg px-2 text-xs font-bold text-[#a92634] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#f2a0a8]"
                        @click="requestDelete(conversation)"
                    >
                        Eliminar conversación
                    </button>
                    <button
                        v-if="confirmingId === conversation.id"
                        type="button"
                        class="min-h-9 rounded-lg px-2 text-xs font-semibold text-[#626979] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:text-[#bdc5d2]"
                        @click="confirmingId = null"
                    >
                        Cancelar
                    </button>
                    <button
                        v-else
                        type="button"
                        class="grid size-9 place-items-center rounded-lg text-[#777f8f] opacity-100 hover:bg-[#fff0f1] hover:text-[#a92634] focus:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] sm:opacity-0 sm:group-hover:opacity-100 dark:hover:bg-[#c62f3d]/10 dark:hover:text-[#f2a0a8]"
                        :aria-label="`Eliminar conversación: ${conversation.title}`"
                        @click="requestDelete(conversation)"
                    >
                        <AppIcon name="trash" class="size-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
