<script setup lang="ts">
import AppIcon from '@/Components/App/AppIcon.vue';
import AssistantActionCard from '@/Components/Assistant/AssistantActionCard.vue';
import { useTypewriter } from '@/composables/useTypewriter';
import type { AssistantAction, AssistantMessage } from '@/types/assistant';
import DOMPurify from 'dompurify';
import { Marked } from 'marked';
import { computed, onUnmounted, watch } from 'vue';

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

const customMarked = new Marked({
    gfm: true,
    breaks: true,
});

customMarked.use({
    renderer: {
        link({ href, text }) {
            if (
                href &&
                (href.includes('cries/pokemon') ||
                    href.endsWith('.ogg') ||
                    href.startsWith('cry:'))
            ) {
                const audioUrl = href.startsWith('cry:')
                    ? `https://raw.githubusercontent.com/PokeAPI/cries/main/cries/pokemon/latest/${href.replace('cry:', '')}.ogg`
                    : href;

                return `<button type="button" data-cry-url="${audioUrl}" class="inline-flex items-center gap-1.5 my-1 rounded-xl border border-line bg-surface-subtle px-3 py-1.5 text-xs font-semibold text-[#172033] shadow-2xs transition-[border-color,background-color,color,transform] hover:border-[#c62f3d] hover:bg-[#fff0f1] hover:text-[#c62f3d] active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] dark:border-white/10 dark:bg-white/10 dark:text-[#f7f4ed] dark:hover:border-[#f08f99] dark:hover:bg-[#c62f3d]/15 dark:hover:text-[#f08f99] cursor-pointer" aria-label="${text}"><svg class="size-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5" /><path d="M15.54 8.46a5 5 0 0 1 0 7.07" /><path d="M19.07 4.93a10 10 0 0 1 0 14.14" /></svg><span>${text}</span></button>`;
            }

            const cleanHref = DOMPurify.sanitize(href);
            return `<a href="${cleanHref}" target="_blank" rel="noopener noreferrer">${text}</a>`;
        },
    },
});

function renderMarkdown(content: string): string {
    const rawHtml = customMarked.parse(content) as string;
    return DOMPurify.sanitize(rawHtml, {
        ADD_ATTR: ['data-cry-url', 'target', 'rel', 'aria-label'],
        ADD_TAGS: ['button', 'svg', 'polygon', 'path', 'span'],
    });
}

let activeChatAudio: HTMLAudioElement | null = null;

function handleMessageContainerClick(event: MouseEvent): void {
    const target = (event.target as HTMLElement | null)?.closest<HTMLButtonElement>('[data-cry-url]');
    if (!target) {
        return;
    }

    const cryUrl = target.getAttribute('data-cry-url');
    if (!cryUrl) {
        return;
    }

    if (activeChatAudio) {
        activeChatAudio.pause();
        activeChatAudio.currentTime = 0;
    }

    const audio = new Audio(cryUrl);
    activeChatAudio = audio;

    target.classList.add('motion-safe:animate-pulse', 'text-[#c62f3d]', 'dark:text-[#f08f99]', 'border-[#c62f3d]', 'dark:border-[#f08f99]');

    const cleanup = () => {
        target.classList.remove('motion-safe:animate-pulse', 'text-[#c62f3d]', 'dark:text-[#f08f99]', 'border-[#c62f3d]', 'dark:border-[#f08f99]');
        activeChatAudio = null;
    };

    audio.onended = cleanup;
    audio.onerror = cleanup;
    audio.play().catch(cleanup);
}

onUnmounted(() => {
    if (activeChatAudio) {
        activeChatAudio.pause();
        activeChatAudio = null;
    }
});

type TimelineItem =
    | { type: 'message'; data: AssistantMessage; key: string }
    | { type: 'action'; data: AssistantAction; key: string };

const timelineItems = computed<TimelineItem[]>(() => {
    const sortedMessages = [...props.messages].sort(
        (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
    );

    const sortedActions = [...props.actions].sort((a, b) => {
        const timeA = new Date(a.created_at || a.expires_at).getTime();
        const timeB = new Date(b.created_at || b.expires_at).getTime();
        return timeA - timeB;
    });

    const result: TimelineItem[] = [];
    let actionIndex = 0;

    for (let i = 0; i < sortedMessages.length; i++) {
        const msg = sortedMessages[i];
        result.push({ type: 'message', data: msg, key: `msg-${msg.id}` });

        if (msg.role === 'assistant') {
            const nextMsg = sortedMessages[i + 1];
            const nextMsgTime = nextMsg ? new Date(nextMsg.created_at).getTime() : Infinity;

            while (actionIndex < sortedActions.length) {
                const action = sortedActions[actionIndex];
                const actionTime = new Date(action.created_at || action.expires_at).getTime();

                if (actionTime < nextMsgTime) {
                    result.push({ type: 'action', data: action, key: `act-${action.id}` });
                    actionIndex++;
                } else {
                    break;
                }
            }
        }
    }

    while (actionIndex < sortedActions.length) {
        const action = sortedActions[actionIndex];
        result.push({ type: 'action', data: action, key: `act-${action.id}` });
        actionIndex++;
    }

    return result;
});
</script>

<template>
    <div class="flex min-h-full flex-col justify-end gap-4 p-4 sm:p-5" @click="handleMessageContainerClick">
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

            <template v-for="item in timelineItems" :key="item.key">
                <div
                    v-if="item.type === 'message'"
                    class="flex"
                    :class="item.data.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[88%] overflow-hidden rounded-2xl text-sm leading-6"
                        :class="item.data.role === 'user' ? 'rounded-br-md bg-[#172033] text-white dark:bg-[#f7f4ed] dark:text-[#172033]' : 'rounded-bl-md border border-line bg-white text-[#303849] dark:border-white/10 dark:bg-white/5 dark:text-[#e8ebf0]'"
                    >
                        <div
                            v-if="item.data.attachments.length > 0"
                            class="grid gap-1.5 p-1.5"
                            :class="item.data.attachments.length > 1 ? 'grid-cols-2' : 'grid-cols-1'"
                        >
                            <img
                                v-for="attachment in item.data.attachments"
                                :key="attachment.id"
                                :src="attachment.url"
                                :alt="`Imagen adjunta: ${attachment.name}`"
                                class="max-h-56 min-h-24 w-full rounded-xl border border-white/15 bg-white/5 object-contain dark:border-[#172033]/15"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                        <p v-if="item.data.role === 'user'" class="whitespace-pre-wrap break-words px-3.5 py-3">{{ item.data.content }}</p>
                        <!-- eslint-disable-next-line vue/no-v-html -->
                        <div v-else class="prose prose-sm max-w-none break-words px-3.5 py-3 dark:prose-invert prose-p:leading-6 prose-p:my-1 prose-headings:font-bold prose-ul:my-1 prose-ol:my-1 prose-li:my-0">
                            <span v-html="renderMarkdown(getDisplayedContent(item.data.id, item.data.content))" />
                            <span
                                v-if="isMessageTyping(item.data.id)"
                                class="inline-block h-3.5 w-1.5 translate-y-0.5 rounded-xs bg-[#c62f3d] align-baseline dark:bg-[#f4b0b7] motion-safe:animate-pulse"
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                </div>

                <AssistantActionCard
                    v-else-if="item.type === 'action'"
                    :action="item.data"
                    :busy="busyActionId === item.data.id"
                    @confirm="$emit('confirmAction', $event)"
                    @cancel="$emit('cancelAction', $event)"
                />
            </template>

            <div v-if="sending" class="flex justify-start" role="status">
                <div class="inline-flex min-h-11 items-center gap-2 rounded-2xl rounded-bl-md border border-line bg-white px-4 text-sm text-[#697180] dark:border-white/10 dark:bg-white/5 dark:text-[#aab4c4]">
                    <span class="size-1.5 rounded-full bg-current motion-safe:animate-pulse" />
                    Pika IA está consultando la Pokédex…
                </div>
            </div>
        </template>
    </div>
</template>
