import assert from 'node:assert/strict';
import test from 'node:test';
import { ApiError } from '@google/genai';
import type { Client } from '@modelcontextprotocol/sdk/client/index.js';
import {
    classifyGeminiFallback,
    generateAssistantResponse,
    generateWithModelFallback,
    multimodalParts,
} from './gemini.js';
import { MODEL_TOOL_NAMES, readToolResult } from './mcp-client.js';

test('Gemini fails safely when its server-side key is missing', async () => {
    const previousKey = process.env.GEMINI_API_KEY;
    delete process.env.GEMINI_API_KEY;

    try {
        await assert.rejects(
            generateAssistantResponse({} as Client, [], 'Resume mi colección'),
            /GEMINI_API_KEY no está configurada/,
        );
    } finally {
        if (previousKey === undefined) {
            delete process.env.GEMINI_API_KEY;
        } else {
            process.env.GEMINI_API_KEY = previousKey;
        }
    }
});

test('the model allow-list excludes the confirmed mutation executor', () => {
    assert.equal(MODEL_TOOL_NAMES.has('execute_confirmed_collection_action'), false);
    assert.equal(MODEL_TOOL_NAMES.has('request_add_pokemon_to_collection'), true);
    assert.equal(MODEL_TOOL_NAMES.has('request_remove_pokemon_from_collection'), true);
    assert.equal(MODEL_TOOL_NAMES.has('request_update_collection_pokemon'), true);
    assert.equal(MODEL_TOOL_NAMES.has('get_pokemon_type_matchups'), true);
    assert.equal(MODEL_TOOL_NAMES.has('get_pokemon_moves'), true);
});

test('multimodal messages keep text and image data in separate parts', () => {
    const parts = multimodalParts('¿Qué Pokémon aparece?', [{
        mimeType: 'image/png',
        data: 'aW1hZ2U=',
    }]);

    assert.deepEqual(parts, [
        { text: '¿Qué Pokémon aparece?' },
        { inlineData: { mimeType: 'image/png', data: 'aW1hZ2U=' } },
    ]);
});

test('tool results prefer structured content and safely parse text content', () => {
    const structured = readToolResult({
        structuredContent: { total: 3 },
        content: [],
    } as never);
    const text = readToolResult({
        content: [{ type: 'text', text: '{"total":2}' }],
    } as never);

    assert.deepEqual(structured, { total: 3 });
    assert.deepEqual(text, { total: 2 });
});

test('transient provider failures are eligible for fallback', () => {
    assert.equal(
        classifyGeminiFallback(new ApiError({ status: 429, message: 'Rate limited' })),
        'rate_limited',
    );
    assert.equal(
        classifyGeminiFallback(new ApiError({ status: 503, message: 'Unavailable' })),
        'provider_unavailable',
    );
    assert.equal(
        classifyGeminiFallback(Object.assign(new Error('Request aborted'), { name: 'AbortError' })),
        'timeout',
    );
    assert.equal(
        classifyGeminiFallback(new TypeError('fetch failed')),
        'connection_error',
    );
});

test('configuration and validation failures do not trigger fallback', () => {
    assert.equal(
        classifyGeminiFallback(new ApiError({ status: 400, message: 'Invalid request' })),
        null,
    );
    assert.equal(
        classifyGeminiFallback(new ApiError({ status: 401, message: 'Invalid API key' })),
        null,
    );
    assert.equal(
        classifyGeminiFallback(new Error('Gemini solicitó una herramienta no permitida.')),
        null,
    );
});

test('a transient primary failure switches once to the configured fallback model', async () => {
    const attempts: string[] = [];
    const response = await generateWithModelFallback(
        'gemini-3.5-flash-lite',
        'gemini-3.1-flash-lite',
        async (model) => {
            attempts.push(model);

            if (model === 'gemini-3.5-flash-lite') {
                throw new ApiError({ status: 503, message: 'Unavailable' });
            }

            return {
                content: 'Respuesta desde el modelo alternativo.',
                model,
                tools: [],
            };
        },
    );

    assert.deepEqual(attempts, ['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite']);
    assert.equal(response.model, 'gemini-3.1-flash-lite');
    assert.deepEqual(response.fallback, {
        from: 'gemini-3.5-flash-lite',
        reason: 'provider_unavailable',
    });
});

test('fallback is blocked after any MCP tool call has started', async () => {
    const attempts: string[] = [];

    await assert.rejects(
        generateWithModelFallback(
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            async (model, onToolCall) => {
                attempts.push(model);
                onToolCall();
                throw new ApiError({ status: 503, message: 'Unavailable after tool use' });
            },
        ),
        /Unavailable after tool use/,
    );

    assert.deepEqual(attempts, ['gemini-3.5-flash-lite']);
});
