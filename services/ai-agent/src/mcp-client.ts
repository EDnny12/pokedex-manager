import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { StreamableHTTPClientTransport } from '@modelcontextprotocol/sdk/client/streamableHttp.js';

export const MODEL_TOOL_NAMES = new Set([
    'get_my_collection',
    'get_my_pokemon',
    'get_collection_summary',
    'search_pokemon_catalog',
    'get_pokemon',
    'compare_pokemon',
    'get_pokemon_forms',
    'get_pokemon_evolution_chain',
    'get_pokemon_type_matchups',
    'get_pokemon_moves',
    'get_move',
    'request_add_pokemon_to_collection',
    'request_remove_pokemon_from_collection',
    'request_update_collection_pokemon',
]);

export async function withMcpClient<T>(
    contextToken: string,
    callback: (client: Client) => Promise<T>,
): Promise<T> {
    const client = new Client({ name: 'pokedex-ai-agent', version: '1.0.0' });
    const transport = new StreamableHTTPClientTransport(
        new URL(process.env.MCP_SERVER_URL ?? 'http://pokedex-mcp:3200/mcp'),
        {
            requestInit: {
                headers: { Authorization: `Bearer ${contextToken}` },
            },
        },
    );

    await client.connect(transport);

    try {
        return await callback(client);
    } finally {
        await client.close();
    }
}

export function readToolResult(result: Awaited<ReturnType<Client['callTool']>>): unknown {
    if (result.structuredContent) {
        return result.structuredContent;
    }

    const content = Array.isArray(result.content) ? result.content : [];
    const text = content
        .filter((item): item is { type: 'text'; text: string } => item.type === 'text')
        .map((item) => item.text)
        .join('\n');

    try {
        return JSON.parse(text);
    } catch {
        return text;
    }
}
