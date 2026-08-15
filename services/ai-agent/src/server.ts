import express from 'express';
import { z } from 'zod';
import { generateAssistantResponse, generateTrainerBio } from './gemini.js';
import { readToolResult, withMcpClient } from './mcp-client.js';

const app = express();
const port = Number(process.env.PORT ?? 3100);
const serviceSecret = process.env.AI_SERVICE_SECRET ?? '';
const imageSchema = z.object({
    mimeType: z.enum(['image/jpeg', 'image/png', 'image/webp']),
    data: z.string().min(4).max(7_100_000).regex(/^[A-Za-z0-9+/]+={0,2}$/),
});

const chatSchema = z.object({
    conversationId: z.string().uuid(),
    contextToken: z.string().min(20),
    history: z.array(z.object({
        role: z.enum(['user', 'assistant']),
        content: z.string().min(1).max(10_000),
        attachments: z.array(imageSchema).max(2).default([]),
    })).max(30),
    message: z.string().min(1).max(2_000),
    attachments: z.array(imageSchema).max(2).default([]),
    requestId: z.string().uuid(),
}).superRefine((value, context) => {
    const encodedBytes = [
        ...value.attachments,
        ...value.history.flatMap((item) => item.attachments),
    ].reduce((total, image) => total + image.data.length, 0);

    if (encodedBytes > 17_000_000) {
        context.addIssue({
            code: 'custom',
            message: 'El contexto visual supera el límite permitido.',
        });
    }
});

const executeSchema = z.object({
    actionId: z.string().uuid(),
    contextToken: z.string().min(20),
    requestId: z.string().uuid(),
});

app.disable('x-powered-by');
app.use(express.json({ limit: process.env.AI_AGENT_BODY_LIMIT ?? '20mb' }));

app.get('/health', (_request, response) => {
    response.json({
        status: 'ok',
        model: process.env.GEMINI_MODEL ?? 'gemini-3.5-flash-lite',
        fallbackModel: process.env.GEMINI_FALLBACK_MODEL ?? 'gemini-3.1-flash-lite',
        geminiConfigured: Boolean(process.env.GEMINI_API_KEY),
    });
});

app.use((request, response, next) => {
    const token = request.headers.authorization?.replace(/^Bearer\s+/i, '');

    if (!serviceSecret || token !== serviceSecret) {
        response.status(401).json({ message: 'No autorizado.' });
        return;
    }

    next();
});

app.post('/chat', async (request, response) => {
    const parsed = chatSchema.safeParse(request.body);

    if (!parsed.success) {
        response.status(422).json({ message: 'La solicitud del chat no es válida.' });
        return;
    }

    const startedAt = performance.now();

    try {
        const result = await withMcpClient(parsed.data.contextToken, (client) =>
            generateAssistantResponse(
                client,
                parsed.data.history,
                parsed.data.message,
                parsed.data.attachments,
            ),
        );
        const durationMs = Math.round(performance.now() - startedAt);

        console.info(JSON.stringify({
            requestId: parsed.data.requestId,
            conversationId: parsed.data.conversationId,
            model: result.model,
            fallbackFrom: result.fallback?.from,
            fallbackReason: result.fallback?.reason,
            tools: result.tools,
            durationMs,
            status: 'ok',
        }));
        response.json({ ...result, durationMs });
    } catch (error) {
        const durationMs = Math.round(performance.now() - startedAt);
        console.error(JSON.stringify({
            requestId: parsed.data.requestId,
            conversationId: parsed.data.conversationId,
            durationMs,
            status: 'error',
            error: error instanceof Error ? error.message : 'Unknown error',
        }));
        response.status(503).json({ message: 'Pika IA no está disponible en este momento.' });
    }
});

app.post('/actions/execute', async (request, response) => {
    const parsed = executeSchema.safeParse(request.body);

    if (!parsed.success) {
        response.status(422).json({ message: 'La acción no es válida.' });
        return;
    }

    try {
        const result = await withMcpClient(parsed.data.contextToken, async (client) => {
            const toolResult = await client.callTool({
                name: 'execute_confirmed_collection_action',
                arguments: { action_id: parsed.data.actionId },
            });

            return readToolResult(toolResult);
        });
        response.json(result);
    } catch (error) {
        console.error(JSON.stringify({
            requestId: parsed.data.requestId,
            actionId: parsed.data.actionId,
            status: 'error',
            error: error instanceof Error ? error.message : 'Unknown error',
        }));
        response.status(503).json({ message: 'No se pudo ejecutar la acción.' });
    }
});

const trainerBioSchema = z.object({
    rank: z.string().min(1).max(50),
    totalPokemon: z.number().int().nonnegative(),
    favorites: z.number().int().nonnegative(),
    dominantType: z.string().nullable().optional(),
    signaturePokemon: z.object({
        name: z.string(),
        displayName: z.string(),
        types: z.array(z.string()),
        isFavorite: z.boolean(),
    }).nullable().optional(),
    party: z.array(z.object({
        name: z.string(),
        displayName: z.string(),
        types: z.array(z.string()),
        isFavorite: z.boolean(),
    })).max(6).default([]),
});

app.post('/trainer/profile-bio', async (request, response) => {
    const parsed = trainerBioSchema.safeParse(request.body);

    if (!parsed.success) {
        response.status(422).json({ message: 'Los datos del entrenador no son válidos.' });
        return;
    }

    try {
        const bio = await generateTrainerBio(parsed.data);
        response.json(bio);
    } catch (error) {
        console.error(JSON.stringify({
            status: 'error',
            endpoint: '/trainer/profile-bio',
            error: error instanceof Error ? error.message : 'Unknown error',
        }));
        response.status(503).json({ message: 'No se pudo generar la biografía del entrenador.' });
    }
});

app.listen(port, '0.0.0.0', () => {
    console.info(JSON.stringify({ service: 'ai-agent', port, status: 'started' }));
});
