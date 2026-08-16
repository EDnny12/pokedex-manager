import type {
    AssistantAction,
    AssistantBootstrap,
    AssistantConversation,
    AssistantMessage,
    AssistantMessagePage,
} from '@/types/assistant';
import type { AppPageProps } from '@/types/page';
import { router, usePage } from '@inertiajs/vue3';
import { computed, readonly, shallowRef, watch } from 'vue';
import { route } from '../../../vendor/tightenco/ziggy';

const conversations = shallowRef<AssistantConversation[]>([]);
const activeConversation = shallowRef<AssistantConversation | null>(null);
const messages = shallowRef<AssistantMessage[]>([]);
const messageCursor = shallowRef<string | null>(null);
const actions = shallowRef<AssistantAction[]>([]);
const loading = shallowRef(false);
const loadingOlderMessages = shallowRef(false);
const sending = shallowRef(false);
const error = shallowRef<string | null>(null);
const initialized = shallowRef(false);
let activeOwnerId: number | null = null;
let ownerRevision = 0;

interface ConversationCacheEntry {
    active_conversation: AssistantConversation;
    messages: AssistantMessage[];
    messageCursor: string | null;
    actions: AssistantAction[];
}

const conversationCache = new Map<string, ConversationCacheEntry>();

function resetState(): void {
    conversations.value = [];
    activeConversation.value = null;
    messages.value = [];
    messageCursor.value = null;
    actions.value = [];
    loading.value = false;
    loadingOlderMessages.value = false;
    sending.value = false;
    error.value = null;
    initialized.value = false;
    conversationCache.clear();
}

function synchronizeOwner(userId: number): void {
    if (activeOwnerId === userId) {
        return;
    }

    activeOwnerId = userId;
    ownerRevision += 1;
    resetState();
}

function isCurrentOwner(revision: number): boolean {
    return revision === ownerRevision;
}

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=').slice(1).join('=')) : '';
}

async function request<T>(url: string, options: RequestInit = {}): Promise<T> {
    const headers = new Headers(options.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-XSRF-TOKEN', xsrfToken());

    if (!(options.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers,
    });
    const payload = await response.json() as T & { message?: string; data?: T };

    if (!response.ok) {
        throw new Error(payload.message || 'No pudimos completar la solicitud.');
    }

    return payload.data ?? payload;
}

async function load(conversationId?: string, silent = false): Promise<void> {
    const revision = ownerRevision;
    if (!silent) {
        loading.value = true;
    }
    error.value = null;

    try {
        if (conversationId) {
            const url = new URL(
                route('assistant.conversations.show', conversationId),
                window.location.origin,
            );
            const payload = await request<{
                active_conversation: AssistantConversation;
                messages: AssistantMessagePage;
                actions: AssistantAction[];
            }>(url.toString());

            if (!isCurrentOwner(revision)) {
                return;
            }

            activeConversation.value = payload.active_conversation;
            messages.value = payload.messages.data;
            messageCursor.value = payload.messages.next_cursor;
            actions.value = payload.actions;

            conversationCache.set(conversationId, {
                active_conversation: payload.active_conversation,
                messages: payload.messages.data,
                messageCursor: payload.messages.next_cursor,
                actions: payload.actions,
            });
        } else {
            const url = new URL(route('assistant.conversations.index'), window.location.origin);
            const bootstrap = await request<AssistantBootstrap>(url.toString());

            if (!isCurrentOwner(revision)) {
                return;
            }

            conversations.value = bootstrap.conversations;
            activeConversation.value = bootstrap.active_conversation;

            if (!silent) {
                messages.value = bootstrap.messages.data;
                messageCursor.value = bootstrap.messages.next_cursor;
            }

            actions.value = bootstrap.actions;
            initialized.value = true;

            if (bootstrap.active_conversation) {
                conversationCache.set(bootstrap.active_conversation.id, {
                    active_conversation: bootstrap.active_conversation,
                    messages: bootstrap.messages.data,
                    messageCursor: bootstrap.messages.next_cursor,
                    actions: bootstrap.actions,
                });
            }
        }
    } catch (exception) {
        if (isCurrentOwner(revision)) {
            error.value = exception instanceof Error ? exception.message : 'No pudimos cargar tus conversaciones.';
        }
    } finally {
        if (isCurrentOwner(revision) && !silent) {
            loading.value = false;
        }
    }
}

async function loadOlderMessages(): Promise<boolean> {
    const revision = ownerRevision;
    const conversationId = activeConversation.value?.id;
    const cursor = messageCursor.value;

    if (!conversationId || !cursor || loadingOlderMessages.value) {
        return false;
    }

    loadingOlderMessages.value = true;
    error.value = null;

    try {
        const url = new URL(
            route('assistant.messages.index', conversationId),
            window.location.origin,
        );
        url.searchParams.set('cursor', cursor);
        const response = await request<{ messages: AssistantMessagePage }>(url.toString());

        if (!isCurrentOwner(revision) || activeConversation.value?.id !== conversationId) {
            return false;
        }

        const existingMessageIds = new Set(messages.value.map((message) => message.id));
        const olderMessages = response.messages.data.filter(
            (message) => !existingMessageIds.has(message.id),
        );

        messages.value = [...olderMessages, ...messages.value];
        messageCursor.value = response.messages.next_cursor;

        if (activeConversation.value) {
            conversationCache.set(conversationId, {
                active_conversation: activeConversation.value,
                messages: messages.value,
                messageCursor: messageCursor.value,
                actions: actions.value,
            });
        }

        return true;
    } catch (exception) {
        if (isCurrentOwner(revision)) {
            error.value = exception instanceof Error
                ? exception.message
                : 'No pudimos cargar los mensajes anteriores.';
        }

        return false;
    } finally {
        if (isCurrentOwner(revision)) {
            loadingOlderMessages.value = false;
        }
    }
}

async function ensureInitialized(): Promise<void> {
    if (!initialized.value) {
        await load();
    }
}

function startNewConversation(): void {
    activeConversation.value = null;
    messages.value = [];
    messageCursor.value = null;
    actions.value = [];
    loading.value = false;
    error.value = null;
}

async function createConversation(): Promise<AssistantConversation | null> {
    const revision = ownerRevision;
    error.value = null;

    try {
        const conversation = await request<AssistantConversation>(route('assistant.conversations.store'), {
            method: 'POST',
            body: JSON.stringify({}),
        });

        if (!isCurrentOwner(revision)) {
            return null;
        }

        conversations.value = [conversation, ...conversations.value];
        activeConversation.value = conversation;
        messages.value = [];
        messageCursor.value = null;
        actions.value = [];

        conversationCache.set(conversation.id, {
            active_conversation: conversation,
            messages: [],
            messageCursor: null,
            actions: [],
        });

        return conversation;
    } catch (exception) {
        if (isCurrentOwner(revision)) {
            error.value = exception instanceof Error ? exception.message : 'No pudimos crear una conversación.';
        }

        return null;
    }
}

async function selectConversation(conversation: AssistantConversation): Promise<void> {
    const cached = conversationCache.get(conversation.id);

    if (cached) {
        activeConversation.value = cached.active_conversation;
        messages.value = cached.messages;
        messageCursor.value = cached.messageCursor;
        actions.value = cached.actions;
        loading.value = false;
        error.value = null;

        await load(conversation.id, true);
    } else {
        activeConversation.value = conversation;
        messages.value = [];
        messageCursor.value = null;
        actions.value = [];
        await load(conversation.id, false);
    }
}

async function deleteConversation(conversation: AssistantConversation): Promise<void> {
    const revision = ownerRevision;
    error.value = null;

    try {
        await request(route('assistant.conversations.destroy', conversation.id), { method: 'DELETE' });

        if (!isCurrentOwner(revision)) {
            return;
        }

        conversationCache.delete(conversation.id);
        conversations.value = conversations.value.filter((item) => item.id !== conversation.id);

        if (activeConversation.value?.id === conversation.id) {
            activeConversation.value = null;
            messages.value = [];
            messageCursor.value = null;
            actions.value = [];

            if (conversations.value[0]) {
                await selectConversation(conversations.value[0]);
            }
        }
    } catch (exception) {
        if (isCurrentOwner(revision)) {
            error.value = exception instanceof Error ? exception.message : 'No pudimos eliminar la conversación.';
        }
    }
}

async function sendMessage(content: string, images: readonly File[] = []): Promise<boolean> {
    const revision = ownerRevision;
    let conversation = activeConversation.value;

    if (!conversation) {
        conversation = await createConversation();
    }

    if (!conversation) {
        return false;
    }

    const clientMessageId = crypto.randomUUID();
    const normalizedContent = content.trim() || (images.length === 1
        ? 'Analiza esta imagen.'
        : 'Analiza estas imágenes.');
    const optimisticMessage: AssistantMessage = {
        id: clientMessageId,
        role: 'user',
        content: normalizedContent,
        metadata: {},
        attachments: [],
        created_at: new Date().toISOString(),
    };
    messages.value = [...messages.value, optimisticMessage];
    sending.value = true;
    error.value = null;

    try {
        const formData = new FormData();
        formData.append('message', content.trim());
        formData.append('client_message_id', clientMessageId);
        images.forEach((image) => formData.append('images[]', image));

        const response = await request<{
            conversation: AssistantConversation;
            user_message: AssistantMessage;
            assistant_message: AssistantMessage;
        }>(route('assistant.messages.store', conversation.id), {
            method: 'POST',
            body: formData,
        });

        if (!isCurrentOwner(revision)) {
            return false;
        }

        messages.value = [
            ...messages.value.filter((message) => message.id !== clientMessageId),
            response.user_message,
            response.assistant_message,
        ];
        activeConversation.value = response.conversation;
        conversations.value = [
            response.conversation,
            ...conversations.value.filter((item) => item.id !== response.conversation.id),
        ];
        await load(response.conversation.id, true);

        return true;
    } catch (exception) {
        if (isCurrentOwner(revision)) {
            messages.value = messages.value.filter((message) => message.id !== clientMessageId);
            error.value = exception instanceof Error ? exception.message : 'Pika IA no pudo responder. Inténtalo de nuevo.';
        }

        return false;
    } finally {
        if (isCurrentOwner(revision)) {
            sending.value = false;
        }
    }
}

async function updateAction(action: AssistantAction, operation: 'confirm' | 'cancel'): Promise<boolean> {
    const revision = ownerRevision;
    error.value = null;

    try {
        const response = await request<{ action: AssistantAction; message: string }>(
            operation === 'confirm'
                ? route('assistant.actions.confirm', action.id)
                : route('assistant.actions.cancel', action.id),
            { method: 'POST', body: JSON.stringify({}) },
        );

        if (!isCurrentOwner(revision)) {
            return false;
        }

        actions.value = actions.value.map((item) => item.id === action.id ? response.action : item);

        if (activeConversation.value) {
            conversationCache.set(activeConversation.value.id, {
                active_conversation: activeConversation.value,
                messages: [...messages.value],
                messageCursor: messageCursor.value,
                actions: actions.value,
            });
        }

        if (operation === 'confirm') {
            const scrollPosition = window.scrollY;
            router.reload({
                onFinish: () => window.scrollTo({ top: scrollPosition }),
            });
        }

        return true;
    } catch (exception) {
        if (isCurrentOwner(revision)) {
            error.value = exception instanceof Error ? exception.message : 'No pudimos actualizar la acción.';
        }

        return false;
    }
}

export function useAssistantChat() {
    const page = usePage<AppPageProps>();
    const synchronizeCurrentOwner = (): void => synchronizeOwner(page.props.auth.user.id);

    synchronizeCurrentOwner();
    watch(() => page.props.auth.user.id, synchronizeOwner);

    return {
        conversations: readonly(conversations),
        activeConversation: readonly(activeConversation),
        messages: readonly(messages),
        actions: readonly(actions),
        loading: readonly(loading),
        loadingOlderMessages: readonly(loadingOlderMessages),
        sending: readonly(sending),
        error: readonly(error),
        hasOlderMessages: computed(() => messageCursor.value !== null),
        hasPendingActions: computed(() => actions.value.some((action) => action.status === 'pending')),
        ensureInitialized: async (): Promise<void> => {
            synchronizeCurrentOwner();
            await ensureInitialized();
        },
        load: async (conversationId?: string): Promise<void> => {
            synchronizeCurrentOwner();
            await load(conversationId);
        },
        loadOlderMessages: async (): Promise<boolean> => {
            synchronizeCurrentOwner();
            return loadOlderMessages();
        },
        createConversation: async (): Promise<AssistantConversation | null> => {
            synchronizeCurrentOwner();
            return createConversation();
        },
        startNewConversation: (): void => {
            synchronizeCurrentOwner();
            startNewConversation();
        },
        selectConversation: async (conversation: AssistantConversation): Promise<void> => {
            synchronizeCurrentOwner();
            await selectConversation(conversation);
        },
        deleteConversation: async (conversation: AssistantConversation): Promise<void> => {
            synchronizeCurrentOwner();
            await deleteConversation(conversation);
        },
        sendMessage: async (content: string, images: readonly File[] = []): Promise<boolean> => {
            synchronizeCurrentOwner();
            return sendMessage(content, images);
        },
        confirmAction: (action: AssistantAction): Promise<boolean> => {
            synchronizeCurrentOwner();
            return updateAction(action, 'confirm');
        },
        cancelAction: (action: AssistantAction): Promise<boolean> => {
            synchronizeCurrentOwner();
            return updateAction(action, 'cancel');
        },
        clearError: (): void => {
            synchronizeCurrentOwner();
            error.value = null;
        },
    };
}
