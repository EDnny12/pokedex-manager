import type { AssistantAction, AssistantMessage } from '@/types/assistant';
import { flushPromises, mount } from '@vue/test-utils';
import { nextTick, shallowRef } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AssistantComposer from './AssistantComposer.vue';
import AssistantMessageList from './AssistantMessageList.vue';
import AssistantPanel from './AssistantPanel.vue';

let messages = shallowRef<AssistantMessage[]>([]);
let actions = shallowRef<AssistantAction[]>([]);
let loading = shallowRef(false);
let sending = shallowRef(false);
let sendMessage = vi.fn(async () => true);

vi.mock('@/composables/useAssistantChat', () => ({
    useAssistantChat: () => ({
        conversations: shallowRef([]),
        activeConversation: shallowRef(null),
        messages,
        actions,
        loading,
        sending,
        error: shallowRef(null),
        ensureInitialized: vi.fn(),
        createConversation: vi.fn(),
        selectConversation: vi.fn(),
        deleteConversation: vi.fn(),
        sendMessage,
        confirmAction: vi.fn(),
        cancelAction: vi.fn(),
        clearError: vi.fn(),
    }),
}));

function message(id: string, role: AssistantMessage['role'], content: string): AssistantMessage {
    return {
        id,
        role,
        content,
        metadata: {},
        attachments: [],
        created_at: '2026-08-14T00:00:00Z',
    };
}

function mountPanel() {
    return mount(AssistantPanel, {
        props: {
            modelValue: false,
        },
        attachTo: document.body,
    });
}

function viewportElement(): HTMLDivElement {
    const viewport = document.body.querySelector<HTMLDivElement>('[aria-live="polite"]');

    if (!viewport) {
        throw new Error('No se encontró el viewport del chat.');
    }

    return viewport;
}

function configureViewport(viewport: HTMLDivElement, scrollHeight: number, clientHeight = 300): void {
    Object.defineProperty(viewport, 'scrollHeight', { configurable: true, value: scrollHeight });
    Object.defineProperty(viewport, 'clientHeight', { configurable: true, value: clientHeight });
}

async function flushViewportUpdate(): Promise<void> {
    await nextTick();
    await nextTick();
}

beforeEach(() => {
    messages = shallowRef<AssistantMessage[]>([]);
    actions = shallowRef<AssistantAction[]>([]);
    loading = shallowRef(false);
    sending = shallowRef(false);
    sendMessage = vi.fn(async () => true);
});

afterEach(() => {
    document.body.innerHTML = '';
});

describe('AssistantPanel', () => {
    it('vacía el campo al enviar y restaura el borrador si la solicitud falla', async () => {
        let resolveSend: (sent: boolean) => void = () => {};
        sendMessage = vi.fn(() => new Promise<boolean>((resolve) => {
            resolveSend = resolve;
        }));
        const wrapper = mountPanel();
        const composer = wrapper.findComponent(AssistantComposer);
        const textarea = composer.get<HTMLTextAreaElement>('textarea');

        await textarea.setValue('Háblame de Eevee');
        await composer.get('form').trigger('submit');
        await nextTick();

        expect(sendMessage).toHaveBeenCalledWith('Háblame de Eevee', []);
        expect(textarea.element.value).toBe('');

        resolveSend(false);
        await flushPromises();

        expect(textarea.element.value).toBe('Háblame de Eevee');
        wrapper.unmount();
    });

    it('mantiene visible el final cuando cambia el contenido o el estado de la respuesta', async () => {
        const wrapper = mountPanel();
        const viewport = viewportElement();

        configureViewport(viewport, 640);
        messages.value = [
            message('1', 'user', 'Háblame de Pikachu.'),
            message('2', 'assistant', 'Pikachu es el Pokémon #025.'),
        ];
        await flushViewportUpdate();

        expect(wrapper.findComponent(AssistantMessageList).props('messages')).toHaveLength(2);
        expect(viewport.scrollTop).toBe(640);

        configureViewport(viewport, 720);
        messages.value = [...messages.value];
        await flushViewportUpdate();

        expect(viewport.scrollTop).toBe(720);

        configureViewport(viewport, 760);
        sending.value = true;
        await flushViewportUpdate();

        expect(viewport.scrollTop).toBe(760);
        wrapper.unmount();
    });

    it('respeta la lectura anterior, pero vuelve al final cuando la persona envía un mensaje', async () => {
        sendMessage = vi.fn(async () => {
            messages.value = [...messages.value, message('3', 'user', '¿Y sus habilidades?')];
            return true;
        });
        const wrapper = mountPanel();
        const viewport = viewportElement();

        configureViewport(viewport, 900);
        viewport.scrollTop = 100;
        viewport.dispatchEvent(new Event('scroll'));
        await nextTick();

        messages.value = [message('1', 'assistant', 'Contenido anterior.')];
        await flushViewportUpdate();
        expect(viewport.scrollTop).toBe(100);

        await wrapper.findComponent(AssistantComposer).vm.$emit('submit', '¿Y sus habilidades?', []);
        await flushViewportUpdate();

        expect(sendMessage).toHaveBeenCalledWith('¿Y sus habilidades?', []);
        expect(viewport.scrollTop).toBe(900);
        wrapper.unmount();
    });
});
