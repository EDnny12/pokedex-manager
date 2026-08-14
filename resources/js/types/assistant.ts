export type AssistantRole = 'user' | 'assistant';
export type AssistantActionType = 'add_pokemon' | 'remove_pokemon';
export type AssistantActionStatus = 'pending' | 'confirmed' | 'cancelled' | 'executed' | 'failed' | 'expired';

export interface AssistantConversation {
    id: string;
    title: string;
    preview?: string | null;
    last_message_at: string | null;
    created_at: string;
}

export interface AssistantMessage {
    id: string;
    role: AssistantRole;
    content: string;
    metadata: Record<string, unknown>;
    attachments: readonly AssistantAttachment[];
    created_at: string;
}

export interface AssistantAttachment {
    id: string;
    name: string;
    mime_type: 'image/jpeg' | 'image/png' | 'image/webp';
    size: number;
    width: number | null;
    height: number | null;
    url: string;
}

export interface AssistantAction {
    id: string;
    type: AssistantActionType;
    status: AssistantActionStatus;
    payload: {
        pokemon_id: number;
        display_name: string;
        image?: string | null;
    };
    expires_at: string;
    executed_at: string | null;
}

export interface AssistantBootstrap {
    conversations: AssistantConversation[];
    active_conversation: AssistantConversation | null;
    messages: AssistantMessage[];
    actions: AssistantAction[];
}
