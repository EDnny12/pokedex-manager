import express from 'express';
import { randomUUID } from 'node:crypto';
import { StreamableHTTPServerTransport } from '@modelcontextprotocol/sdk/server/streamableHttp.js';
import { createServer } from './tools.js';

const app = express();
const port = Number(process.env.PORT ?? 3200);

app.disable('x-powered-by');
app.use(express.json({ limit: '64kb' }));

app.get('/health', (_request, response) => {
    response.json({ status: 'ok' });
});

app.post('/mcp', async (request, response) => {
    const contextToken = request.headers.authorization?.replace(/^Bearer\s+/i, '');

    if (!contextToken) {
        response.status(401).json({ message: 'No autorizado.' });
        return;
    }

    const server = createServer(contextToken);
    const transport = new StreamableHTTPServerTransport({
        sessionIdGenerator: undefined,
        enableJsonResponse: true,
    });

    response.on('close', () => {
        void transport.close();
        void server.close();
    });

    try {
        await server.connect(transport);
        await transport.handleRequest(request, response, request.body);
    } catch (error) {
        console.error(JSON.stringify({
            requestId: request.headers['x-request-id'] ?? randomUUID(),
            status: 'error',
            error: error instanceof Error ? error.message : 'Unknown error',
        }));

        if (!response.headersSent) {
            response.status(500).json({
                jsonrpc: '2.0',
                error: { code: -32603, message: 'Error interno.' },
                id: null,
            });
        }
    }
});

app.listen(port, '0.0.0.0', () => {
    console.info(JSON.stringify({ service: 'pokedex-mcp', port, status: 'started' }));
});
