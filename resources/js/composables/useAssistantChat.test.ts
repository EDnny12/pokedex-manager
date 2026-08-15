import type { AssistantBootstrap } from '@/types/assistant';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { useAssistantChat } from './useAssistantChat';

const { page, reload } = vi.hoisted(() => ({
    page: {
        props: {
            auth: { user: { id: 101 } },
        },
    },
    reload: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { reload },
    usePage: () => page,
}));

vi.mock('../../../vendor/tightenco/ziggy', () => ({
    route: (name: string): string => '/' + name,
}));

afterEach(() => {
    vi.unstubAllGlobals();
    reload.mockClear();
});

describe('useAssistantChat', () => {
    it('descarta el estado compartido cuando cambia el usuario autenticado', async () => {
        const bootstrap: AssistantBootstrap = {
            conversations: [{
                id: 'conversation-user-101',
                title: 'Equipo eléctrico',
                last_message_at: null,
                created_at: '2026-08-13T00:00:00Z',
            }],
            active_conversation: null,
            messages: {
                data: [],
                next_cursor: null,
                has_more: false,
            },
            actions: [],
        };
        vi.stubGlobal('fetch', vi.fn(async () => ({
            ok: true,
            json: async () => bootstrap,
        })));

        page.props.auth.user.id = 101;
        const firstUserChat = useAssistantChat();
        await firstUserChat.load();
        expect(firstUserChat.conversations.value).toHaveLength(1);

        page.props.auth.user.id = 202;
        const secondUserChat = useAssistantChat();
        expect(secondUserChat.conversations.value).toEqual([]);
        expect(secondUserChat.messages.value).toEqual([]);
        expect(secondUserChat.actions.value).toEqual([]);
    });

    it('antepone páginas anteriores sin duplicar mensajes', async () => {
        page.props.auth.user.id = 303;
        const recentMessage = {
            id: 'message-2',
            role: 'assistant' as const,
            content: 'Mensaje reciente.',
            metadata: {},
            attachments: [],
            created_at: '2026-08-13T00:00:02Z',
        };
        const olderMessage = {
            id: 'message-1',
            role: 'user' as const,
            content: 'Mensaje anterior.',
            metadata: {},
            attachments: [],
            created_at: '2026-08-13T00:00:01Z',
        };
        const responses = [
            {
                conversations: [{
                    id: 'conversation-user-303',
                    title: 'Historial',
                    last_message_at: recentMessage.created_at,
                    created_at: '2026-08-13T00:00:00Z',
                }],
                active_conversation: {
                    id: 'conversation-user-303',
                    title: 'Historial',
                    last_message_at: recentMessage.created_at,
                    created_at: '2026-08-13T00:00:00Z',
                },
                messages: {
                    data: [recentMessage],
                    next_cursor: 'older-cursor',
                    has_more: true,
                },
                actions: [],
            },
            {
                messages: {
                    data: [olderMessage, recentMessage],
                    next_cursor: null,
                    has_more: false,
                },
            },
        ];
        vi.stubGlobal('fetch', vi.fn(async () => ({
            ok: true,
            json: async () => responses.shift(),
        })));

        const chat = useAssistantChat();
        await chat.load('conversation-user-303');
        await chat.loadOlderMessages();

        expect(chat.messages.value.map((message) => message.id)).toEqual(['message-1', 'message-2']);
        expect(chat.hasOlderMessages.value).toBe(false);
    });
});
