<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import AssistantActionCard from '@/Components/Assistant/AssistantActionCard.vue';
import { useTypewriter } from '@/composables/useTypewriter';
import type { AssistantAction, AssistantMessage } from '@/types/assistant';
import DOMPurify from 'dompurify';
import { marked } from 'marked';
import { watch } from 'vue';

const props = defineProps<{
    messages: readonly AssistantMessage[];
    actions: readonly AssistantAction[];
    loading: boolean;
    hasOlderMessages: boolean;
    loadingOlderMessages: boolean;
    sending: boolean;
    busyActionId: string | null;
}>();

const emit = defineEmits<{
    suggestion: [message: string];
    scan: [];
    loadOlder: [];
    confirmAction: [action: AssistantAction];
    cancelAction: [action: AssistantAction];
    streamProgress: [];
}>();

const suggestions = [
    'Analiza mi colección',
    '¿Qué tipos me faltan?',
    '¿Cómo puedo equilibrar mi colección?',
    'Compara Pikachu con Jolteon',
];

const { typeMessage, isMessageTyping, getDisplayedContent } = useTypewriter();
const seenMessageIds = new Set<string>(props.messages.map((message) => message.id));

watch(
    () => props.messages,
    (newMessages) => {
        if (newMessages.length === 0) {
            seenMessageIds.clear();
            return;
        }

        const lastMessage = newMessages[newMessages.length - 1];
        if (
            lastMessage &&
            lastMessage.role === 'assistant' &&
            !seenMessageIds.has(lastMessage.id)
        ) {
            typeMessage(
                lastMessage.id,
                lastMessage.content,
                () => emit('streamProgress'),
            );
        }

        for (const message of newMessages) {
            seenMessageIds.add(message.id);
        }
    },
);

function renderMarkdown(content: string): string {
    return DOMPurify.sanitize(marked.parse(content) as string);
}
</script>

<template>
    <div class="flex min-h-full flex-col justify-end gap-4 p-4 sm:p-5">
        <div v-if="loading" aria-label="Cargando conversación" class="flex flex-col gap-3">
            <div class="h-14 w-4/5 rounded-2xl bg-skeleton motion-safe:animate-pulse dark:bg-white/10" />
            <div class="ml-auto h-12 w-3/5 rounded-2xl bg-[#ead6d8] motion-safe:animate-pulse dark:bg-[#572630]" />
            <span class="sr-only">Cargando conversación…</span>
        </div>

        <div v-else-if="messages.length === 0" class="my-auto flex flex-col items-center gap-5 py-7 text-center">
            <div class="grid size-14 place-items-center rounded-2xl bg-[#172033] text-white shadow-sm dark:bg-[#f7f4ed] dark:text-[#172033]">
                <AppIcon name="sparkles" class="size-6" />
            </div>
            <div class="max-w-xs">
                <h2 class="text-xl font-bold tracking-[-0.03em]">¿Qué quieres descubrir?</h2>
                <p class="mt-2 text-sm leading-6 text-[#697180] dark:text-[#aab4c4]">Pregunta por tu colección, compara Pokémon o prepara cambios que tú confirmarás.</p>
            </div>
            <button
                type="button"
                class="group flex min-h-16 w-full items-center gap-3 rounded-2xl border border-[#d8aeb3] bg-[#fff7f7] p-3 text-left transition-[border-color,background-color,transform] hover:border-[#c62f3d] hover:bg-[#fff0f1] active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-[#8f3944]/60 dark:bg-[#321a21] dark:hover:border-[#d85b68] dark:hover:bg-[#3a1d25]"
                aria-describedby="assistant-scan-description"
                data-testid="assistant-image-scan"
                @click="$emit('scan')"
            >
                <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-[#c62f3d] text-white">
                    <AppIcon name="image" class="size-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-[#7f1f2b] dark:text-[#f4b0b7]">Identificar con una imagen</span>
                    <span id="assistant-scan-description" class="mt-0.5 block text-xs leading-5 text-[#785d62] dark:text-[#d0a5aa]">Adjunta una foto o captura y Pika IA verificará el Pokémon en la Pokédex.</span>
                </span>
                <AppIcon name="arrow-left" class="size-4 shrink-0 rotate-180 text-[#a43a46] transition-transform group-hover:translate-x-0.5 dark:text-[#e78992]" aria-hidden="true" />
            </button>
            <div class="flex w-full flex-col gap-2">
                <button
                    v-for="suggestion in suggestions"
                    :key="suggestion"
                    type="button"
                    class="min-h-11 rounded-xl border border-line bg-white px-3 py-2.5 text-left text-sm font-semibold text-[#3f4757] transition-colors hover:border-line-strong hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-white/10 dark:bg-white/5 dark:text-[#e8ebf0] dark:hover:bg-white/10"
                    @click="$emit('suggestion', suggestion)"
                >
                    {{ suggestion }}
                </button>
            </div>
        </div>

        <template v-else>
            <div v-if="hasOlderMessages" class="flex justify-center">
                <button
                    type="button"
                    class="min-h-11 rounded-xl border border-line bg-white px-4 py-2 text-sm font-semibold text-[#505867] transition-colors hover:border-line-strong hover:bg-surface-subtle focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] disabled:cursor-wait disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-[#d6dbe4] dark:hover:bg-white/10"
                    :disabled="loadingOlderMessages"
                    :aria-busy="loadingOlderMessages"
                    @click="$emit('loadOlder')"
                >
                    {{ loadingOlderMessages ? 'Cargando mensajes anteriores…' : 'Cargar mensajes anteriores' }}
                </button>
            </div>

            <div
                v-for="message in messages"
                :key="message.id"
                class="flex"
                :class="message.role === 'user' ? 'justify-end' : 'justify-start'"
            >
                <div
                    class="max-w-[88%] overflow-hidden rounded-2xl text-sm leading-6"
                    :class="message.role === 'user' ? 'rounded-br-md bg-[#172033] text-white dark:bg-[#f7f4ed] dark:text-[#172033]' : 'rounded-bl-md border border-line bg-white text-[#303849] dark:border-white/10 dark:bg-white/5 dark:text-[#e8ebf0]'"
                >
                    <div
                        v-if="message.attachments.length > 0"
                        class="grid gap-1.5 p-1.5"
                        :class="message.attachments.length > 1 ? 'grid-cols-2' : 'grid-cols-1'"
                    >
                        <img
                            v-for="attachment in message.attachments"
                            :key="attachment.id"
                            :src="attachment.url"
                            :alt="`Imagen adjunta: ${attachment.name}`"
                            class="max-h-56 min-h-24 w-full rounded-xl border border-white/15 bg-white/5 object-contain dark:border-[#172033]/15"
                            loading="lazy"
                            decoding="async"
                        />
                    </div>
                    <p v-if="message.role === 'user'" class="whitespace-pre-wrap break-words px-3.5 py-3">{{ message.content }}</p>
                    <!-- eslint-disable-next-line vue/no-v-html -->
                    <div v-else class="prose prose-sm max-w-none break-words px-3.5 py-3 dark:prose-invert prose-p:leading-6 prose-p:my-1 prose-headings:font-bold prose-ul:my-1 prose-ol:my-1 prose-li:my-0">
                        <span v-html="renderMarkdown(getDisplayedContent(message.id, message.content))" />
                        <span
                            v-if="isMessageTyping(message.id)"
                            class="inline-block h-3.5 w-1.5 translate-y-0.5 rounded-xs bg-[#c62f3d] align-baseline dark:bg-[#f4b0b7] motion-safe:animate-pulse"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </div>

            <div v-if="sending" class="flex justify-start" role="status">
                <div class="inline-flex min-h-11 items-center gap-2 rounded-2xl rounded-bl-md border border-line bg-white px-4 text-sm text-[#697180] dark:border-white/10 dark:bg-white/5 dark:text-[#aab4c4]">
                    <span class="size-1.5 rounded-full bg-current motion-safe:animate-pulse" />
                    Pika IA está consultando la Pokédex…
                </div>
            </div>
        </template>

        <AssistantActionCard
            v-for="action in actions"
            :key="action.id"
            :action="action"
            :busy="busyActionId === action.id"
            @confirm="$emit('confirmAction', $event)"
            @cancel="$emit('cancelAction', $event)"
        />
    </div>
</template>
