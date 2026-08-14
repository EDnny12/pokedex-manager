<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import AssistantComposer from '@/Components/Assistant/AssistantComposer.vue';
import AssistantConversationList from '@/Components/Assistant/AssistantConversationList.vue';
import AssistantMessageList from '@/Components/Assistant/AssistantMessageList.vue';
import { useAssistantChat } from '@/composables/useAssistantChat';
import type { AssistantAction, AssistantConversation } from '@/types/assistant';
import { nextTick, shallowRef, useTemplateRef, watch } from 'vue';

const open = defineModel<boolean>({ required: true });
const dialog = useTemplateRef<HTMLDialogElement>('dialog');
const closeButton = useTemplateRef<HTMLButtonElement>('closeButton');
const messageViewport = useTemplateRef<HTMLDivElement>('messageViewport');
const composer = useTemplateRef<InstanceType<typeof AssistantComposer>>('composer');
const draft = shallowRef('');
const draftImages = shallowRef<File[]>([]);
const showHistory = shallowRef(false);
const busyActionId = shallowRef<string | null>(null);

const {
    conversations,
    activeConversation,
    messages,
    actions,
    loading,
    sending,
    error,
    ensureInitialized,
    createConversation,
    selectConversation,
    deleteConversation,
    sendMessage,
    confirmAction,
    cancelAction,
    clearError,
} = useAssistantChat();

watch(open, async (isOpen) => {
    if (isOpen && !dialog.value?.open) {
        dialog.value?.showModal();
        await ensureInitialized();
        await nextTick();
        closeButton.value?.focus();
        scrollToLatest();
    } else if (!isOpen && dialog.value?.open) {
        dialog.value.close();
    }
});

watch(() => messages.value.length, async () => {
    await nextTick();
    scrollToLatest();
});

function close(): void {
    open.value = false;
    showHistory.value = false;
}

function scrollToLatest(): void {
    messageViewport.value?.scrollTo({ top: messageViewport.value.scrollHeight, behavior: 'smooth' });
}

async function startConversation(): Promise<void> {
    await createConversation();
    showHistory.value = false;
}

async function chooseConversation(conversation: AssistantConversation): Promise<void> {
    await selectConversation(conversation);
    showHistory.value = false;
    await nextTick();
    scrollToLatest();
}

async function submit(message: string, images: File[] = []): Promise<void> {
    const sent = await sendMessage(message, images);

    if (sent) {
        draft.value = '';
        draftImages.value = [];
    }
}

function startImageScan(): void {
    draft.value = 'Identifica el Pokémon de esta imagen y verifica su ficha en la Pokédex.';
    composer.value?.openImagePicker();
}

async function handleAction(action: AssistantAction, operation: 'confirm' | 'cancel'): Promise<void> {
    busyActionId.value = action.id;

    try {
        if (operation === 'confirm') {
            await confirmAction(action);
        } else {
            await cancelAction(action);
        }
    } finally {
        busyActionId.value = null;
    }
}
</script>

<template>
    <Teleport to="body">
        <dialog
            ref="dialog"
            aria-labelledby="assistant-title"
            class="m-0 h-[100dvh] max-h-none w-full max-w-none overflow-hidden bg-transparent p-0 backdrop:bg-[#0b1019]/55 sm:fixed sm:inset-auto sm:bottom-5 sm:right-5 sm:h-[min(46rem,calc(100dvh-2.5rem))] sm:w-[min(28rem,calc(100vw-2.5rem))] sm:rounded-[1.75rem] sm:shadow-[0_30px_90px_rgba(11,16,25,0.28)] lg:right-7"
            @close="open = false"
            @click="($event.target === dialog) && close()"
        >
            <div class="relative flex h-full min-h-0 overflow-hidden bg-[#f6f7f9] text-[#172033] sm:rounded-[1.75rem] sm:border sm:border-line dark:bg-[#0e1420] dark:text-[#f7f4ed] sm:dark:border-white/10">
                <aside
                    v-if="showHistory"
                    class="absolute inset-0 z-20 flex min-h-0 w-full flex-col border-r border-line bg-white p-3 sm:relative sm:w-72 sm:shrink-0 dark:border-white/10 dark:bg-[#131b29]"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <p class="text-sm font-bold">Historial</p>
                        <button type="button" class="grid size-11 place-items-center rounded-xl hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] sm:hidden dark:hover:bg-white/5" aria-label="Cerrar historial" @click="showHistory = false">
                            <AppIcon name="close" class="size-5" />
                        </button>
                    </div>
                    <AssistantConversationList
                        :conversations="conversations"
                        :active-id="activeConversation?.id"
                        @select="chooseConversation"
                        @delete="deleteConversation"
                    />
                </aside>

                <section class="flex min-w-0 flex-1 flex-col">
                    <header class="flex min-h-16 items-center gap-2 border-b border-line bg-white px-3 dark:border-white/10 dark:bg-[#131b29]">
                        <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:hover:bg-white/5" :aria-expanded="showHistory" aria-label="Ver historial de conversaciones" @click="showHistory = !showHistory">
                            <AppIcon name="menu" class="size-5" />
                        </button>
                        <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-[#172033] text-white dark:bg-[#f7f4ed] dark:text-[#172033]">
                            <AppIcon name="sparkles" class="size-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h1 id="assistant-title" class="truncate text-sm font-bold">Lía</h1>
                            <p class="truncate text-xs text-[#777f8f] dark:text-[#9aa5b5]">Asistente de tu Pokédex</p>
                        </div>
                        <button type="button" class="grid size-11 shrink-0 place-items-center rounded-xl hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:hover:bg-white/5" aria-label="Nueva conversación" @click="startConversation">
                            <AppIcon name="plus" class="size-5" />
                        </button>
                        <button ref="closeButton" type="button" class="grid size-11 shrink-0 place-items-center rounded-xl hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:hover:bg-white/5" aria-label="Cerrar chat con Lía" @click="close">
                            <AppIcon name="close" class="size-5" />
                        </button>
                    </header>

                    <div v-if="error" class="mx-3 mt-3 flex items-start justify-between gap-3 rounded-xl border border-[#e1b8bd] bg-[#fff0f1] px-3 py-2.5 text-sm text-[#8e2430] dark:border-[#8f3944]/60 dark:bg-[#341a22] dark:text-[#f2a0a8]" role="alert">
                        <span>{{ error }}</span>
                        <button type="button" class="grid size-8 shrink-0 place-items-center rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d]" aria-label="Cerrar mensaje" @click="clearError">
                            <AppIcon name="close" class="size-4" />
                        </button>
                    </div>

                    <div ref="messageViewport" class="min-h-0 flex-1 overflow-y-auto overscroll-contain" aria-live="polite">
                        <AssistantMessageList
                            :messages="messages"
                            :actions="actions"
                            :loading="loading"
                            :sending="sending"
                            :busy-action-id="busyActionId"
                            @suggestion="submit"
                            @scan="startImageScan"
                            @confirm-action="handleAction($event, 'confirm')"
                            @cancel-action="handleAction($event, 'cancel')"
                        />
                    </div>

                    <AssistantComposer ref="composer" v-model="draft" v-model:images="draftImages" :sending="sending" @submit="submit" />
                </section>
            </div>
        </dialog>
    </Teleport>
</template>

<style scoped>
dialog[open] {
    animation: assistant-fade 140ms ease-out;
}

@keyframes assistant-fade {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (prefers-reduced-motion: reduce) {
    dialog[open] {
        animation: none;
    }
}
</style>
