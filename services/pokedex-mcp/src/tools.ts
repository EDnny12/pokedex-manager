import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { z } from 'zod';
import { callLaravel } from './laravel-client.js';

function result(payload: Record<string, unknown>) {
    return {
        content: [{ type: 'text' as const, text: JSON.stringify(payload) }],
        structuredContent: payload,
    };
}

export function createServer(contextToken: string): McpServer {
    const server = new McpServer(
        { name: 'pokedex-manager', version: '1.0.0' },
        {
            instructions: 'Retrieve authoritative application data through Laravel. Collection change request tools only create pending confirmations; they never mutate the collection directly.',
        },
    );

    server.registerTool('get_my_collection', {
        description: 'Returns the authenticated person\'s current collection with optional search, type, favorite, date, sorting, and limit filters.',
        inputSchema: {
            search: z.string().max(80).optional(),
            type: z.string().max(30).optional(),
            favorite: z.boolean().optional(),
            added_after: z.iso.date().optional(),
            sort: z.enum(['recent', 'oldest', 'recently_updated', 'name', 'pokedex']).default('recent'),
            limit: z.number().int().min(1).max(50).default(20),
        },
        annotations: { readOnlyHint: true },
    }, async (input) => result(await callLaravel(contextToken, 'collection', { query: input })));

    server.registerTool('get_my_pokemon', {
        description: 'Returns one Pokémon owned by the authenticated person, resolved by Pokédex number, canonical name, or nickname.',
        inputSchema: { pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]) },
        annotations: { readOnlyHint: true },
    }, async ({ pokemon }) => result(await callLaravel(contextToken, 'collection/pokemon', { query: { pokemon } })));

    server.registerTool('get_collection_summary', {
        description: 'Returns an efficient collection summary with totals, favorites, type distribution, missing types, and highest available stats.',
        inputSchema: {},
        annotations: { readOnlyHint: true },
    }, async () => result(await callLaravel(contextToken, 'collection/summary')));

    server.registerTool('search_pokemon_catalog', {
        description: 'Searches the Pokédex by name or number and can combine exact type, ability, and generation filters. Returns a bounded result set with collection membership.',
        inputSchema: {
            query: z.string().max(80).optional(),
            type: z.string().max(30).optional(),
            ability: z.string().max(80).optional(),
            generation: z.string().max(30).optional(),
            limit: z.number().int().min(1).max(20).default(10),
        },
        annotations: { readOnlyHint: true },
    }, async (input) => result(await callLaravel(contextToken, 'catalog', { query: input })));

    server.registerTool('get_pokemon', {
        description: 'Returns the available profile, exact form identity, types, abilities, dimensions, and base stats for one Pokémon.',
        inputSchema: { pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]) },
        annotations: { readOnlyHint: true },
    }, async ({ pokemon }) => result(await callLaravel(contextToken, 'pokemon', { query: { pokemon } })));

    server.registerTool('compare_pokemon', {
        description: 'Returns comparable authoritative data for two to four Pokémon from the collection or catalog.',
        inputSchema: {
            pokemon: z.array(z.union([z.string().min(1).max(80), z.number().int().positive()])).min(2).max(4),
        },
        annotations: { readOnlyHint: true },
    }, async ({ pokemon }) => result(await callLaravel(contextToken, 'compare', { method: 'POST', body: { pokemon } })));

    server.registerTool('get_pokemon_forms', {
        description: 'Returns the exact selected form and the catalog forms or varieties that belong to the same Pokémon species. Use it before making claims about regional or alternate forms.',
        inputSchema: { pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]) },
        annotations: { readOnlyHint: true },
    }, async ({ pokemon }) => result(await callLaravel(contextToken, 'pokemon/forms', { query: { pokemon } })));

    server.registerTool('get_pokemon_evolution_chain', {
        description: 'Returns the species-level evolution tree and the available evolution conditions for one Pokémon or form.',
        inputSchema: { pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]) },
        annotations: { readOnlyHint: true },
    }, async ({ pokemon }) => result(await callLaravel(contextToken, 'pokemon/evolution-chain', { query: { pokemon } })));

    server.registerTool('get_pokemon_type_matchups', {
        description: 'Calculates current defensive weaknesses, resistances, and immunities for the exact Pokémon form, including combined multipliers for dual types.',
        inputSchema: { pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]) },
        annotations: { readOnlyHint: true },
    }, async ({ pokemon }) => result(await callLaravel(contextToken, 'pokemon/type-matchups', { query: { pokemon } })));

    server.registerTool('get_pokemon_moves', {
        description: 'Returns a bounded learnset for one exact Pokémon form. Optionally filter by learning method and version group; use get_move for one move\'s battle details.',
        inputSchema: {
            pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]),
            learn_method: z.enum(['level-up', 'machine', 'tutor', 'egg']).optional(),
            version_group: z.string().min(1).max(50).optional(),
            limit: z.number().int().min(1).max(30).default(20),
        },
        annotations: { readOnlyHint: true },
    }, async (input) => result(await callLaravel(contextToken, 'pokemon/moves', { query: input })));

    server.registerTool('get_move', {
        description: 'Returns authoritative battle details for one move, including type, damage class, power, accuracy, PP, priority, target, and its short effect when available.',
        inputSchema: { move: z.union([z.string().min(1).max(80), z.number().int().positive()]) },
        annotations: { readOnlyHint: true },
    }, async ({ move }) => result(await callLaravel(contextToken, 'move', { query: { pokemon: move } })));

    server.registerTool('request_add_pokemon_to_collection', {
        description: 'Creates a pending request to add one exact Pokémon. Use only after an explicit user request; a separate confirmation card is required and the collection is not changed.',
        inputSchema: { pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]) },
        annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false },
    }, async ({ pokemon }) => result(await callLaravel(contextToken, 'actions', {
        method: 'POST',
        body: { type: 'add_pokemon', pokemon },
    })));

    server.registerTool('request_remove_pokemon_from_collection', {
        description: 'Creates a pending request to remove one exact owned Pokémon. Use only after an explicit user request; a separate confirmation card is required and the collection is not changed.',
        inputSchema: { pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]) },
        annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: false },
    }, async ({ pokemon }) => result(await callLaravel(contextToken, 'actions', {
        method: 'POST',
        body: { type: 'remove_pokemon', pokemon },
    })));

    server.registerTool('request_update_collection_pokemon', {
        description: 'Creates a pending request to change the nickname, notes, or favorite state of one owned Pokémon. Use only after an explicit request; a separate confirmation card is required and no data changes yet.',
        inputSchema: {
            pokemon: z.union([z.string().min(1).max(80), z.number().int().positive()]),
            changes: z.object({
                nickname: z.string().trim().max(60).nullable().optional(),
                notes: z.string().trim().max(2000).nullable().optional(),
                is_favorite: z.boolean().optional(),
            }).refine(
                (changes) => Object.keys(changes).length > 0,
                { message: 'At least one collection field must be provided.' },
            ),
        },
        annotations: { readOnlyHint: false, destructiveHint: false, idempotentHint: false },
    }, async ({ pokemon, changes }) => result(await callLaravel(contextToken, 'actions', {
        method: 'POST',
        body: { type: 'update_pokemon', pokemon, changes },
    })));

    server.registerTool('execute_confirmed_collection_action', {
        description: 'Executes an action already confirmed and authorized by Laravel. This tool is internal and is never exposed to Gemini.',
        inputSchema: { action_id: z.string().uuid() },
        annotations: { readOnlyHint: false, destructiveHint: true, idempotentHint: true },
    }, async ({ action_id }) => result(await callLaravel(contextToken, `actions/${action_id}/execute`, { method: 'POST' })));

    return server;
}
