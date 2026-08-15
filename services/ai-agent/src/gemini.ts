import { ApiError, GoogleGenAI, ThinkingLevel, type Content, type FunctionDeclaration, type Part } from '@google/genai';
import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { MODEL_TOOL_NAMES, readToolResult } from './mcp-client.js';
import { SYSTEM_PROMPT } from './prompt.js';

export type ImageAttachment = {
    mimeType: 'image/jpeg' | 'image/png' | 'image/webp';
    data: string;
};

type ChatHistoryItem = {
    role: 'user' | 'assistant';
    content: string;
    attachments: ImageAttachment[];
};

export type AssistantResponse = {
    content: string;
    model: string;
    tools: string[];
    fallback?: {
        from: string;
        reason: GeminiFallbackReason;
    };
};

export type GeminiFallbackReason = 'rate_limited' | 'provider_unavailable' | 'timeout' | 'connection_error';

type ModelAttempt = (
    model: string,
    onToolCall: () => void,
) => Promise<AssistantResponse>;

const CONNECTION_ERROR_CODES = new Set([
    'ECONNREFUSED',
    'ECONNRESET',
    'ENETDOWN',
    'ENETUNREACH',
    'EHOSTUNREACH',
    'EPIPE',
    'ETIMEDOUT',
    'UND_ERR_CONNECT_TIMEOUT',
    'UND_ERR_HEADERS_TIMEOUT',
    'UND_ERR_BODY_TIMEOUT',
    'UND_ERR_SOCKET',
]);

export function multimodalParts(message: string, attachments: ImageAttachment[]): Part[] {
    return [
        { text: message },
        ...attachments.map((attachment) => ({
            inlineData: {
                mimeType: attachment.mimeType,
                data: attachment.data,
            },
        })),
    ];
}

export function classifyGeminiFallback(error: unknown): GeminiFallbackReason | null {
    if (error instanceof ApiError) {
        if (error.status === 408) {
            return 'timeout';
        }

        if (error.status === 429) {
            return 'rate_limited';
        }

        if (error.status >= 500 && error.status <= 599) {
            return 'provider_unavailable';
        }

        return null;
    }

    if (!(error instanceof Error)) {
        return null;
    }

    if (error.name === 'AbortError' || error.name === 'TimeoutError') {
        return 'timeout';
    }

    const errorWithCause = error as Error & {
        code?: unknown;
        cause?: { code?: unknown };
    };
    const code = typeof errorWithCause.code === 'string'
        ? errorWithCause.code
        : errorWithCause.cause?.code;

    if (typeof code === 'string' && CONNECTION_ERROR_CODES.has(code)) {
        return code.includes('TIMEOUT') || code === 'ETIMEDOUT'
            ? 'timeout'
            : 'connection_error';
    }

    if (/timed? out/i.test(error.message)) {
        return 'timeout';
    }

    return /fetch failed|network error|socket hang up|connection (?:reset|refused)/i.test(error.message)
        ? 'connection_error'
        : null;
}

export async function generateWithModelFallback(
    primaryModel: string,
    fallbackModel: string,
    attempt: ModelAttempt,
): Promise<AssistantResponse> {
    let toolCallStarted = false;

    try {
        return await attempt(primaryModel, () => {
            toolCallStarted = true;
        });
    } catch (error) {
        const reason = classifyGeminiFallback(error);

        if (!reason || toolCallStarted || fallbackModel === '' || fallbackModel === primaryModel) {
            throw error;
        }

        const response = await attempt(fallbackModel, () => {});

        return {
            ...response,
            fallback: {
                from: primaryModel,
                reason,
            },
        };
    }
}

export async function generateAssistantResponse(
    client: Client,
    history: ChatHistoryItem[],
    message: string,
    attachments: ImageAttachment[] = [],
): Promise<AssistantResponse> {
    const apiKey = process.env.GEMINI_API_KEY;

    if (!apiKey) {
        throw new Error('GEMINI_API_KEY no está configurada.');
    }

    const primaryModel = process.env.GEMINI_MODEL?.trim() || 'gemini-3.5-flash-lite';
    const fallbackModel = process.env.GEMINI_FALLBACK_MODEL?.trim() || 'gemini-3.1-flash-lite';
    const ai = new GoogleGenAI({
        apiKey,
        httpOptions: { timeout: Number(process.env.GEMINI_TIMEOUT_MS ?? 40_000) },
    });
    const availableTools = (await client.listTools()).tools.filter((tool) => MODEL_TOOL_NAMES.has(tool.name));
    const declarations: FunctionDeclaration[] = availableTools.map((tool) => ({
        name: tool.name,
        description: tool.description,
        parametersJsonSchema: tool.inputSchema,
    }));
    const chatHistory: Content[] = history.map((item) => ({
        role: item.role === 'assistant' ? 'model' : 'user',
        parts: multimodalParts(item.content, item.attachments),
    }));

    return generateWithModelFallback(
        primaryModel,
        fallbackModel,
        (model, onToolCall) => generateWithModel(
            ai,
            client,
            declarations,
            chatHistory,
            message,
            attachments,
            model,
            onToolCall,
        ),
    );
}

async function generateWithModel(
    ai: GoogleGenAI,
    client: Client,
    declarations: FunctionDeclaration[],
    chatHistory: Content[],
    message: string,
    attachments: ImageAttachment[],
    model: string,
    onToolCall: () => void,
): Promise<AssistantResponse> {
    const chat = ai.chats.create({
        model,
        history: chatHistory,
        config: {
            systemInstruction: SYSTEM_PROMPT,
            tools: [{ functionDeclarations: declarations }],
            thinkingConfig: {
                includeThoughts: false,
                thinkingLevel: ThinkingLevel.MINIMAL,
            },
        },
    });
    const usedTools: string[] = [];
    let response = await chat.sendMessage({ message: multimodalParts(message, attachments) });

    for (let round = 0; round < 6; round += 1) {
        const functionCalls = response.functionCalls ?? [];

        if (functionCalls.length === 0) {
            return {
                content: response.text?.trim() || 'No pude preparar una respuesta útil con los datos disponibles.',
                model,
                tools: usedTools,
            };
        }

        for (const functionCall of functionCalls) {
            if (!functionCall.name || !MODEL_TOOL_NAMES.has(functionCall.name)) {
                throw new Error('Gemini solicitó una herramienta no permitida.');
            }

            usedTools.push(functionCall.name);
            onToolCall();
        }

        const functionResponses = await Promise.all(
            functionCalls.map(async (functionCall) => {
                const result = await client.callTool({
                    name: functionCall.name!,
                    arguments: functionCall.args ?? {},
                });

                return {
                    functionResponse: {
                        id: functionCall.id,
                        name: functionCall.name,
                        response: { result: readToolResult(result) },
                    },
                };
            }),
        );

        response = await chat.sendMessage({ message: functionResponses });
    }

    throw new Error('Se alcanzó el límite seguro de llamadas a herramientas.');
}
