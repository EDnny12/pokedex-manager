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
            messages: [],
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
});
