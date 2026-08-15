import { ApiError, GoogleGenAI, ThinkingLevel, Type, type Content, type FunctionDeclaration, type Part } from '@google/genai';
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

export interface TrainerCollectionSummary {
    rank: string;
    totalPokemon: number;
    favorites: number;
    dominantType?: string | null;
    signaturePokemon?: {
        name: string;
        displayName: string;
        types: string[];
        isFavorite: boolean;
    } | null;
    party: Array<{
        name: string;
        displayName: string;
        types: string[];
        isFavorite: boolean;
    }>;
}

export interface TrainerBioResponse {
    headline: string;
    description: string;
}

export const TRAINER_BIO_SYSTEM_PROMPT = `<role>
You are an expert Pokémon Trainer Card chronicler and biographer for Pokédex Manager.
</role>

<critical_rules>
- Language requirement: All user-facing string values MUST be written in clear, natural Spanish.
- Grounding: The supplied structured context is the strict boundary of truth. Do not invent Pokémon, types, battle roles, stats, or unstated traits not provided in the input.
- "headline" constraints: A creative, brief title in Spanish (2 to 4 words, e.g. "Domador de las Llamas", "Estratega de Tipo Planta", "Coleccionista Versátil").
- "description" constraints: Exactly ONE natural, engaging sentence in Spanish summarizing the trainer's identity and collection style using only characteristics explicitly supported by the supplied context.
</critical_rules>`;

export async function generateTrainerBio(
    summary: TrainerCollectionSummary,
): Promise<TrainerBioResponse> {
    const apiKey = process.env.GEMINI_API_KEY;
    if (!apiKey) {
        throw new Error('GEMINI_API_KEY no está configurada.');
    }

    const primaryModel = process.env.GEMINI_MODEL?.trim() || 'gemini-3.5-flash-lite';
    const fallbackModel = process.env.GEMINI_FALLBACK_MODEL?.trim() || 'gemini-3.1-flash-lite';
    const ai = new GoogleGenAI({
        apiKey,
        httpOptions: { timeout: Number(process.env.GEMINI_TIMEOUT_MS ?? 15_000) },
    });

    const userPrompt = `<trainer_collection_context>
Rank: ${summary.rank}
Total Pokémon: ${summary.totalPokemon}
Favorites: ${summary.favorites}
Dominant Type: ${summary.dominantType ?? 'None'}
Signature Pokémon: ${summary.signaturePokemon ? `${summary.signaturePokemon.displayName} (${summary.signaturePokemon.types.join('/')})` : 'None'}
Party: ${summary.party.map((p) => `${p.displayName} (${p.types.join('/')})`).join(', ') || 'None'}
</trainer_collection_context>

<examples>
Example 1:
Context: Rank: Experto, Dominant Type: fire, Signature Pokémon: Charizard (fire/flying)
Output: { "headline": "Domador de las Llamas", "description": "Entrenador de rango Experto con una clara afinidad por el tipo Fuego y Charizard como Pokémon insignia." }

Example 2:
Context: Rank: Líder, Dominant Type: water, Signature Pokémon: Blastoise (water)
Output: { "headline": "Comandante de las Mareas", "description": "Entrenador de rango Líder especializado en Pokémon de tipo Agua, encabezado por Blastoise." }

Example 3:
Context: Rank: Entrenador, Dominant Type: None, Signature Pokémon: Pikachu (electric)
Output: { "headline": "Estratega Polivalente", "description": "Entrenador con un equipo equilibrado y diverso, guiado por Pikachu como Pokémon insignia." }
</examples>

<task>
Based exclusively on the trainer_collection_context above, generate the headline and description in Spanish.
</task>`;

    const generate = async (model: string): Promise<TrainerBioResponse> => {
        const response = await ai.models.generateContent({
            model,
            contents: [{ role: 'user', parts: [{ text: userPrompt }] }],
            config: {
                systemInstruction: { parts: [{ text: TRAINER_BIO_SYSTEM_PROMPT }] },
                thinkingConfig: { thinkingLevel: ThinkingLevel.MINIMAL },
                responseMimeType: 'application/json',
                responseSchema: {
                    type: Type.OBJECT,
                    description: 'Trainer license card identity containing headline and descriptive bio in Spanish.',
                    properties: {
                        headline: {
                            type: Type.STRING,
                            description: 'Creative short title (2-4 words in Spanish).',
                        },
                        description: {
                            type: Type.STRING,
                            description: 'Exactly one natural sentence in Spanish summarizing the trainer identity and collection style.',
                        },
                    },
                    required: ['headline', 'description'],
                },
            },
        });

        const parsed = JSON.parse(response.text?.trim() || '{}');
        if (!parsed.headline || !parsed.description) {
            throw new Error('Respuesta de perfil de entrenador no válida.');
        }

        return {
            headline: String(parsed.headline).trim(),
            description: String(parsed.description).trim(),
        };
    };

    try {
        return await generate(primaryModel);
    } catch (error) {
        if (fallbackModel && fallbackModel !== primaryModel) {
            return await generate(fallbackModel);
        }
        throw error;
    }
}


