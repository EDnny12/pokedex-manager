import assert from 'node:assert/strict';
import test from 'node:test';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';
import { createServer } from './tools.js';

test('MCP server can be created with an opaque context token', () => {
    const server = createServer('opaque-context-token');
    assert.ok(server);
});

test('MCP exposes the bounded tool set without accepting user identity', async () => {
    const server = createServer('opaque-context-token');
    const client = new Client({ name: 'pokedex-mcp-test', version: '1.0.0' });
    const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair();

    await Promise.all([
        server.connect(serverTransport),
        client.connect(clientTransport),
    ]);

    try {
        const tools = (await client.listTools()).tools;

        assert.deepEqual(tools.map((tool) => tool.name).sort(), [
            'compare_pokemon',
            'execute_confirmed_collection_action',
            'get_collection_summary',
            'get_my_collection',
            'get_my_pokemon',
            'get_pokemon',
            'request_add_pokemon_to_collection',
            'request_remove_pokemon_from_collection',
            'search_pokemon_catalog',
        ]);

        for (const tool of tools) {
            const properties = tool.inputSchema.properties ?? {};
            assert.equal(Object.hasOwn(properties, 'user_id'), false, `${tool.name} no debe aceptar user_id`);
        }

        const malformed = await client.callTool({
            name: 'compare_pokemon',
            arguments: { pokemon: ['pikachu'] },
        });

        assert.equal(malformed.isError, true);
    } finally {
        await client.close();
        await server.close();
    }
});
